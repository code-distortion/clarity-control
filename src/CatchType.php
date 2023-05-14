<?php

namespace CodeDistortion\ClarityControl;

use CodeDistortion\ClarityControl\Exceptions\ClarityControlInitialisationException;
use CodeDistortion\ClarityControl\Support\Support;
use CodeDistortion\Staticall\Staticall;

/**
 * Define how exceptions should be caught and dealt with.
 *
 * @codingStandardsIgnoreStart
 *
 * @method static self catch(string|string[] $exceptionType, string|string[] ...$exceptionType2) Specify the types of exceptions to catch.
 * @method static self match(string|string[] $matches, string|string[] ...$matches2) Specify string/s the exception message must 'match.
 * @method static self matchRegex(string|string[] $matches, string|string[] ...$matches2) Specify regex string/s the exception message must 'match.
 * @method static self callback(callable $callback) Specify a callback to run when an exception occurs.
 * @method static self callbacks(callable|callable[] $callback, callable|callable[] ...$callback2) Specify callbacks to run when an exception occurs.
 * @method static self known(string|string[] $known, string|string[] ...$known2) Specify issue/s that the exception is known to belong to.
 * @method static self channel(string $channel) Specify a channel to log to.
 * @method static self channels(string|string[] $channel, string|string[] ...$channel2) Specify channels to log to.
 * @method static self level(string $level) Specify the log reporting level.
 * @method static self debug() Set the log reporting level to "debug".
 * @method static self info() Set the log reporting level to "info".
 * @method static self notice() Set the log reporting level to "notice".
 * @method static self warning() Set the log reporting level to "warning".
 * @method static self error() Set the log reporting level to "error".
 * @method static self critical() Set the log reporting level to "critical".
 * @method static self alert() Set the log reporting level to "alert".
 * @method static self emergency() Set the log reporting level to "emergency".
 * @method static self report(boolean $report = true) Specify that exceptions should be reported.
 * @method static self dontReport() Specify that exceptions should not be reported.
 * @method static self rethrow(boolean|callable $rethrow = true) Specify whether caught exceptions should be re-thrown, or a callable to make the decision.
 * @method static self dontRethrow() Specify that any caught exceptions should not be re-thrown.
 * @method static self suppress() Suppress any exceptions - don't report and don't rethrow them.
 * @method static self default(mixed $default = true) Specify the default value to return when an exception occurs.
 * @method static self finally(?callable $finally) Specify a callable to run after the main callable (whether an exception occurred or not).
 *
 * @codingStandardsIgnoreEnd
 */
class CatchType
{
    use Staticall;



    /** @var string[] The types of exceptions to pick up. */
    protected array $exceptionClasses = [];

    /** @var string[] The exception message must match one of these (when set). */
    protected array $matchStrings = [];

    /** @var string[] The exception message must match one of these regexes (when set). */
    protected array $matchRegexes = [];

    /** @var callable[] Callbacks to run when triggered. */
    protected array $callbacks = [];

    /** @var string[] The issues this exception relates to. */
    protected array $known = [];

    /** @var string[] The channels to log to. */
    protected array $channels = [];

    /** @var string|null The log reporting level to use. */
    protected ?string $level = null;

    /** @var boolean|null Whether to report the issue (using the framework's reporting mechanism). */
    protected ?bool $report = null;

    /** @var boolean|callable|null Whether to rethrow the exception or not, or a callable to make the decision. */
    protected $rethrow = null;

    /** @var boolean Whether the default value has been set or not. */
    protected bool $defaultIsSet = false;

    /** @var mixed The default value to return when an exception occurs. */
    protected mixed $default = null;

    /** @var callable|null The callable to run afterwards, regardless of whether an exception occurred or not. */
    protected $finally = null;



    /**
     * Specify the types of exceptions to catch.
     *
     * @param string|string[] $exceptionType     The exception classes to catch.
     * @param string|string[] ...$exceptionType2 The exception classes to catch.
     * @return $this
     */
    private function callCatch(string|array $exceptionType, string|array ...$exceptionType2): self
    {
        /** @var string[] $exceptionClasses */
        $exceptionClasses = Support::normaliseArgs($this->exceptionClasses, func_get_args());
        $this->exceptionClasses = $exceptionClasses;

        return $this;
    }



    /**
     * Specify string/s the exception message must match.
     *
     * @param string|string[] $match     The string/s the exception message needs to match.
     * @param string|string[] ...$match2 The string/s the exception message needs to match.
     * @return $this
     */
    private function callMatch(string|array $match, string|array ...$match2): self
    {
        /** @var string[] $matchStrings */
        $matchStrings = Support::normaliseArgs($this->matchStrings, func_get_args());
        $this->matchStrings = $matchStrings;

        return $this;
    }



    /**
     * Specify regex string/s the exception message must match.
     *
     * @param string|string[] $match     The regex string/s the exception message needs to match.
     * @param string|string[] ...$match2 The regex string/s the exception message needs to match.
     * @return $this
     */
    private function callMatchRegex(string|array $match, string|array ...$match2): self
    {
        /** @var string[] $matchRegexes */
        $matchRegexes = Support::normaliseArgs($this->matchRegexes, func_get_args());
        $this->matchRegexes = $matchRegexes;

        return $this;
    }



    /**
     * Specify a callback to run when an exception occurs.
     *
     * @param callable $callback The callback to run.
     * @return $this
     */
    private function callCallback(callable $callback): self
    {
        $this->callbacks([$callback]);

        return $this;
    }

    /**
     * Specify callbacks to run when an exception occurs.
     *
     * @param callable|callable[] $callback     The callback/s to run.
     * @param callable|callable[] ...$callback2 The callback/s to run.
     * @return $this
     */
    private function callCallbacks(callable|array $callback, callable|array ...$callback2): self
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseArgs($this->callbacks, func_get_args());
        $this->callbacks = $callbacks;

        return $this;
    }



    /**
     * Specify issue/s that the exception is known to belong to.
     *
     * @param string|string[] $known     The issue/s this exception is known to belong to.
     * @param string|string[] ...$known2 The issue/s this exception is known to belong to.
     * @return $this
     */
    private function callKnown(string|array $known, string|array ...$known2): self
    {
        /** @var string[] $known */
        $known = Support::normaliseArgs($this->known, func_get_args());
        $this->known = $known;

        return $this;
    }



    /**
     * Specify a channel to log to.
     *
     * @param string $channel The channel to log to.
     * @return $this
     */
    private function callChannel(string $channel): self
    {
        $this->channels([$channel]);

        return $this;
    }

    /**
     * Specify channels to log to.
     *
     * @param string|string[] $channel     The channel/s to log to.
     * @param string|string[] ...$channel2 The channel/s to log to.
     * @return $this
     */
    private function callChannels(string|array $channel, string|array ...$channel2): self
    {
        /** @var string[] $channels */
        $channels = Support::normaliseArgs($this->channels, func_get_args());
        $this->channels = $channels;

        return $this;
    }



    /**
     * Specify the log reporting level.
     *
     * @param string $level The log-level to use.
     * @return $this
     * @throws ClarityControlInitialisationException When an invalid level is specified.
     */
    private function callLevel(string $level): self
    {
        if (!in_array($level, Settings::LOG_LEVELS)) {
            throw ClarityControlInitialisationException::levelNotAllowed($level);
        }

        $this->level = $level;

        return $this;
    }

    /**
     * Set the log reporting level to "debug".
     *
     * @return $this
     */
    private function callDebug(): self
    {
        $this->level = Settings::REPORTING_LEVEL_DEBUG;

        return $this;
    }

    /**
     * Set the log reporting level to "info".
     *
     * @return $this
     */
    private function callInfo(): self
    {
        $this->level = Settings::REPORTING_LEVEL_INFO;

        return $this;
    }

    /**
     * Set the log reporting level to "notice".
     *
     * @return $this
     */
    private function callNotice(): self
    {
        $this->level = Settings::REPORTING_LEVEL_NOTICE;

        return $this;
    }

    /**
     * Set the log reporting level to "warning".
     *
     * @return $this
     */
    private function callWarning(): self
    {
        $this->level = Settings::REPORTING_LEVEL_WARNING;

        return $this;
    }

    /**
     * Set the log reporting level to "error".
     *
     * @return $this
     */
    private function callError(): self
    {
        $this->level = Settings::REPORTING_LEVEL_ERROR;

        return $this;
    }

    /**
     * Set the log reporting level to "critical".
     *
     * @return $this
     */
    private function callCritical(): self
    {
        $this->level = Settings::REPORTING_LEVEL_CRITICAL;

        return $this;
    }

    /**
     * Set the log reporting level to "alert".
     *
     * @return $this
     */
    private function callAlert(): self
    {
        $this->level = Settings::REPORTING_LEVEL_ALERT;

        return $this;
    }

    /**
     * Set the log reporting level to "emergency".
     *
     * @return $this
     */
    private function callEmergency(): self
    {
        $this->level = Settings::REPORTING_LEVEL_EMERGENCY;

        return $this;
    }



    /**
     * Specify that exceptions should be reported.
     *
     * @param boolean $report Whether to report exceptions or not.
     * @return $this
     */
    private function callReport(bool $report = true): self
    {
        $this->report = $report;

        return $this;
    }

    /**
     * Specify that exceptions should not be reported.
     *
     * @return $this
     */
    private function callDontReport(): self
    {
        $this->report = false;

        return $this;
    }



    /**
     * Specify whether caught exceptions should be re-thrown, or a callable to make the decision.
     *
     * @param boolean|callable $rethrow Whether to rethrow exceptions or not, or a callable to make the decision.
     * @return $this
     */
    private function callRethrow(bool|callable $rethrow = true): self
    {
        $this->rethrow = $rethrow;

        return $this;
    }

    /**
     * Specify that caught exceptions should not be re-thrown.
     *
     * @return $this
     */
    private function callDontRethrow(): self
    {
        $this->rethrow = false;

        return $this;
    }



    /**
     * Suppress any exceptions - don't report and don't rethrow them.
     *
     * @return $this
     */
    private function callSuppress(): self
    {
        $this->report = false;
        $this->rethrow = false;

        return $this;
    }



    /**
     * Specify the default value to return when an exception occurs.
     *
     * @param mixed $default The default value to use.
     * @return $this
     */
    private function callDefault(mixed $default): self
    {
        $this->default = $default;
        $this->defaultIsSet = true;

        return $this;
    }



    /**
     * Specify a callable to run after the main callable (whether an exception occurred or not).
     *
     * @param callable|null $finally The callable to run.
     *
     * @return $this
     */
    private function callFinally(?callable $finally): self
    {
        $this->finally =& $finally;

        return $this;
    }
}
