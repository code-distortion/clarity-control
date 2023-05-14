<?php

namespace CodeDistortion\ClarityControl;

use CodeDistortion\ClarityContext\API\ContextAPI;
use CodeDistortion\ClarityContext\API\MetaCallStackAPI;
use CodeDistortion\ClarityContext\Context;
use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityContext\Support\InternalSettings;
use CodeDistortion\ClarityControl\Exceptions\ClarityControlRuntimeException;
use CodeDistortion\ClarityControl\Support\ControlTraits\HasCatchTypes;
use CodeDistortion\ClarityControl\Support\ControlTraits\HasGlobalCallbacks;
use CodeDistortion\ClarityControl\Support\Inspector;
use Throwable;

/**
 * Runs a closure for the caller, catching and reporting exceptions that occur.
 */
class Control
{
    use HasCatchTypes;
    use HasGlobalCallbacks;



    /** @var callable The callable to run. */
    private $callable;

    /** @var callable|null The callable to run afterwards, regardless of whether an exception occurred or not. */
    private $finally = null;

    /** @var mixed Passed-by-reference, will be updated with the exception that occurred (when relevant). */
    private mixed $exceptionHolder; /** @phpstan-ignore-line stops "$exceptionHolder is never read, only written.". */

    /** @var boolean Whether the caller called run(…) rather than prepare(…) or not. */
    private bool $instantiatedUsingRun;



    /**
     * Perform some initialisation.
     *
     * @param callable      $callable             The callable to run.
     * @param callable|null $finally              The callable to run after the main callable (whether an exception
     *                                            occurred or not).
     * @param boolean       $instantiatedUsingRun Whether the caller called ->run(..) (which runs execute() straight
     *                                            away) or not.
     * @param mixed         $default              The default value to return if an exception occurs.
     * @param boolean       $defaultWasSpecified  Whether the caller specified the default value or not.
     * @return $this
     * @see run()
     * @see prepare()
     */
    private function init(
        callable $callable,
        ?callable $finally,
        bool $instantiatedUsingRun,
        mixed $default,
        bool $defaultWasSpecified,
    ): self {

        $this->callable =& $callable;
        $this->finally =& $finally;

        $this->instantiatedUsingRun = $instantiatedUsingRun;

        $this->hasCatchTypesInit($default, $defaultWasSpecified);

        return $this;
    }



    /**
     * Run a callable straight away, catch & report any exceptions (depending on the configuration).
     *
     * @param callable      $callable The callable to run.
     * @param mixed         $default  The default value to return if an exception occurs.
     * @param callable|null $finally  The callable to run after the main callable (whether an exception occurred or not)
     *                                .
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public static function run(callable $callable, mixed $default = null, ?callable $finally = null): mixed
    {
        $defaultWasSpecified = func_num_args() >= 2;

        return (new self())->init($callable, $finally, true, $default, $defaultWasSpecified)->execute();
    }

    /**
     * Create a new Control instance, and prime it with the callback ready to run when execute() is called.
     *
     * @param callable $callable The callable to run.
     * @param mixed    $default  The default value to return if an exception occurs.
     * @return self
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public static function prepare(callable $callable, mixed $default = null, ?callable $finally = null): mixed
    {
        $defaultWasSpecified = func_num_args() >= 2;

        return (new self())->init($callable, $finally, false, $default, $defaultWasSpecified);
    }



    /**
     * Specify a callable to run after the main callable (whether an exception occurred or not).
     *
     * @param callable|null $finally The callable to run.
     *
     * @return $this
     */
    public function finally(?callable $finally): static
    {
        $this->finally =& $finally;

        return $this;
    }

    /**
     * Let the caller capture the exception in a variable that's passed by reference.
     *
     * @param mixed $exception Pass-by-reference parameter that is updated with the exception (when relevant).
     *
     * @return $this
     */
    public function getException(mixed &$exception): static
    {
        $this->exceptionHolder =& $exception;

        return $this;
    }



    /**
     * Execute the callable, and catch & report any exceptions (depending on the set-up and configuration).
     *
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    public function execute(): mixed
    {
        $stepsBack = $this->instantiatedUsingRun
            ? 2
            : 1;

        $metaData = [
            'known' => [],
        ];

        MetaCallStackAPI::pushMetaData(
            InternalSettings::META_DATA_TYPE__CONTROL_CALL,
            spl_object_id($this),
            $metaData,
            $stepsBack
        );

        $this->exceptionHolder = null;
        $finally = $this->finally;
        try {

            return Framework::depInj()->call($this->callable);

        } catch (Throwable $e) {

            // let the caller access the exception via the variable they passed by reference
            $this->exceptionHolder = $e;

            $inspector = $this->pickMatchingCatchType($e);

            // use the "finally" callable from the catch-type if it was set
            $finally = $inspector?->getFinally() ?? $finally;

            return $this->processException($e, $inspector);

        } finally {

            if ($finally) {
                Framework::depInj()->call($finally);
            }
        }
    }

    /**
     * Process the exception.
     *
     * @param Throwable      $e         The exception that occurred.
     * @param Inspector|null $inspector The catch-type that was matched.
     * @return mixed
     * @throws Throwable Exceptions that weren't supposed to be caught.
     */
    private function processException(Throwable $e, ?Inspector $inspector): mixed
    {
        // re-throw the exception if it wasn't supposed to be caught
        if (is_null($inspector)) {
            throw $e;
        }

        $metaData = [
            'known' => $inspector->resolveKnown(),
        ];

        // update with the "known" details now that they've been resolved
        MetaCallStackAPI::replaceMetaData(
            InternalSettings::META_DATA_TYPE__CONTROL_CALL,
            spl_object_id($this),
            $metaData
        );

        $context = $this->runCallbacksReportRethrow($e, $inspector);

        return $this->resolveDefaultValue($context, $inspector);
    }



    /**
     * Gather the callbacks to run.
     *
     * @param Inspector $inspector The catch-type that was matched.
     * @return callable[]
     */
    private function gatherCallbacks(Inspector $inspector): array
    {
        return array_merge($this->getGlobalCallbacks(), $inspector->resolveCallbacks());
    }



    /**
     * Run the callbacks, reporting, and then rethrow the exception if necessary.
     *
     * @param Throwable $e         The exception that occurred.
     * @param Inspector $inspector The catch-type that was matched.
     * @return Context|null
     * @throws Throwable When the exception should be rethrown.
     * @throws ClarityControlRuntimeException When a rethrow callback returns an invalid value.
     */
    private function runCallbacksReportRethrow(Throwable $e, Inspector $inspector): ?Context
    {
        $shouldReport = $inspector->shouldReport();

        $rethrowException = self::resolveExceptionToRethrow($inspector->pickRethrow(), $e);

        // make sure *something* should happen
        // when these are off, the callbacks aren't run
        if ((!$shouldReport) && (!$rethrowException)) {
            return null;
        }



        $callbacks = $this->gatherCallbacks($inspector);
        $context = null;
        if ((count($callbacks) || $shouldReport)) { // the only circumstances where a Context is needed

            $context = ContextAPI::buildContextFromException($e, $inspector->hasKnown(), spl_object_id($this))
                ->setReport($shouldReport)
                ->setRethrow($rethrowException ?? false)
                ->setDefault($inspector->resolveDefault());

            $channels = $inspector->resolveChannels();
            if ($channels) {
                $context->setChannels($channels);
            }

            $level = $inspector->resolveLevel();
            if (!is_null($level)) {
                $context->setLevel($level);
            }
        }

        // don't bother building + storing a context object
        // when the only thing left to do is rethrow
        if (!$context) {
//            $this->runReporting($inspector->shouldReport(), $e);
//            $this->runRethrow($inspector->shouldRethrow(), $e);
//            return;
            throw $e;
        }



        try {

            $this->runCallbacks($context, $e, $callbacks);
            // the $context may have been updated by the callbacks, so use its values instead of $inspector's
            $this->runReporting($context->getReport(), $e);
            $this->runRethrow($context->getRethrow(), $e);

        } finally {
            ContextAPI::forgetExceptionContext($e);
        }

        return $context;
    }



    /**
     * Run the callbacks.
     *
     * @param Context    $context   The Context instance to make available to the callbacks.
     * @param Throwable  $e         The exception to report.
     * @param callable[] $callbacks The callbacks to run.
     * @return void
     */
    private function runCallbacks(Context $context, Throwable $e, array $callbacks): void
    {
        foreach ($callbacks as $callback) {

            // check if the callbacks should continue to be called
            if ((!$context->getReport()) && (!$context->getRethrow())) {
                return; // don't continue
            }

            $this->runCallback($e, $callback);
        }
    }



    /**
     * Run a callback.
     *
     * @param Throwable $e        The exception to report.
     * @param callable  $callback The callback to run.
     * @return void
     */
    private function runCallback(Throwable $e, callable $callback): void
    {
        Framework::depInj()->call($callback, ['exception' => $e, 'e' => $e]);
    }

    /**
     * Report the exception, if needed.
     *
     * @param boolean   $report Whether the exception should be reported or not.
     * @param Throwable $e      The exception that occurred.
     * @return void
     */
    private function runReporting(bool $report, Throwable $e): void
    {
        if (!$report) {
            return;
        }

        report($e);
    }

    /**
     * Rethrow the exception, if needed.
     *
     * @param boolean|callable|Throwable $rethrow Whether the exception should be rethrown or not.
     * @param Throwable                  $e       The exception that occurred.
     * @return void
     * @throws ClarityControlRuntimeException When a rethrow callback returns an invalid value.
     * @throws Throwable When the exception should be rethrown.
     */
    private function runRethrow(bool|callable|Throwable $rethrow, Throwable $e): void
    {
        $rethrowException = self::resolveExceptionToRethrow($rethrow, $e);

        if ($rethrowException) {
            throw $rethrowException;
        }
    }



    /**
     * Determine the default value to return (if it was set).
     *
     * @param Context|null $context   The context that was built.
     * @param Inspector    $inspector The catch-type that was matched.
     * @return mixed
     */
    private function resolveDefaultValue(?Context $context, Inspector $inspector): mixed
    {
        $default = $context?->getDefault()
            ?? $inspector->resolveDefault();

        return is_callable($default)
            ? Framework::depInj()->call($default)
            : $default;
    }

    /**
     * Resolve which Exception (Throwable) to rethrow.
     *
     * @param boolean|callable|Throwable|null $rethrow Whether to rethrow the exception or not, a callable to make the
     *                                                 decision, or the exception to rethrow itself.
     * @param Throwable                       $e       The exception that occurred.
     * @return Throwable|null
     * @throws ClarityControlRuntimeException When a rethrow callback returns an invalid value.
     */
    public static function resolveExceptionToRethrow(bool|callable|Throwable|null $rethrow, Throwable $e): ?Throwable
    {
        if (is_callable($rethrow)) {
            $rethrow = Framework::depInj()->call($rethrow, ['exception' => $e, 'e' => $e]);
        }

        if (is_null($rethrow)) {
            return null;
        }

        if ($rethrow === false) {
            return null;
        }

        if ($rethrow === true) {
            return $e;
        }

        if ($rethrow instanceof Throwable) {
            return $rethrow;
        }

        throw ClarityControlRuntimeException::invalidRethrowValue();
    }
}
