<?php

namespace CodeDistortion\ClarityControl\Tests\Unit;

use CodeDistortion\ClarityContext\API\ContextAPI;
use CodeDistortion\ClarityContext\Clarity;
use CodeDistortion\ClarityContext\Context;
use CodeDistortion\ClarityContext\Support\CallStack\Frame;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\CallMeta;
use CodeDistortion\ClarityContext\Support\Environment;
use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityContext\Support\InternalSettings;
use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Exceptions\ClarityControlInitialisationException;
use CodeDistortion\ClarityControl\Exceptions\ClarityControlRuntimeException;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Support\Inspector;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use CodeDistortion\ClarityControl\Tests\Support\MethodCall;
use CodeDistortion\ClarityControl\Tests\Support\MethodCalls;
use DivisionByZeroError;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Test the Control class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ControlUnitTest extends LaravelTestCase
{
    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';

    /** @var string|null The exception to be triggered (if any). Put here to reduce the space taken when passing. */
    private static ?string $currentExceptionToTrigger;



    /**
     * Test that Control operates properly with the given combinations of ways it can be called.
     *
     * @test
     * @dataProvider controlMethodCallsDataProvider
     *
     * @param MethodCalls       $initMethodCalls               Methods to call when initialising the Control object.
     * @param class-string|null $exceptionToTrigger            The exception type to trigger (if any).
     * @param boolean           $expectExceptionUponInit       Expect exception thrown when initialising.
     * @param boolean           $expectCallbackToBeRun         Except the exception callback to be run?.
     * @param boolean           $expectExceptionToBeLogged     Expect the exception to be logged?.
     * @param boolean           $expectExceptionThrownToCaller Except the exception to be thrown to the caller?.
     * @param mixed             $defaultReturn                 The default return value.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the Control class.
     */
    public static function test_that_control_method_calls_operate_properly(
        MethodCalls $initMethodCalls,
        ?string $exceptionToTrigger,
        bool $expectExceptionUponInit,
        bool $expectCallbackToBeRun,
        bool $expectExceptionToBeLogged,
        bool $expectExceptionThrownToCaller,
        mixed $defaultReturn,
    ): void {

        /** @var ?Throwable $theExceptionToThrow */
        $theExceptionToThrow = !is_null($exceptionToTrigger)
            ? new $exceptionToTrigger(self::$exceptionMessage)
            : null;

        // set up the closure to run
        $intendedReturnValue = mt_rand();
        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount, $intendedReturnValue, $theExceptionToThrow) {
            $closureRunCount++;

            return is_null($theExceptionToThrow)
                ? $intendedReturnValue
                : throw $theExceptionToThrow;
        };



        $exceptionCallbackWasRun = false;
        $exceptionCallbackRunCount = [];
        $exceptionCallbackCount = 0;



        // initialise the Control object
        $control = Control::prepare($closure);
        $control->getException($exception);
        $exceptionWasThrownUponInit = false;
        try {
            foreach ($initMethodCalls->getCalls() as $methodCall) {

                $method = $methodCall->getMethod();
                $args = $methodCall->getArgs();

                // place the exception callback into the args for calls to callback() / callbacks()
                foreach ($args as $index => $arg) {
                    if ((in_array($method, ['callback', 'callbacks'])) && ($arg ?? null)) {

                        $exceptionCallbackRunCount[$exceptionCallbackCount] = 0;

                        $args[$index] = function () use (
                            &$exceptionCallbackWasRun,
                            &$exceptionCallbackRunCount,
                            $exceptionCallbackCount,
                        ) {
                            $exceptionCallbackWasRun = true;
                            $exceptionCallbackRunCount[$exceptionCallbackCount]++;
                        };

                        $exceptionCallbackCount++;
                    }
                }

                $toCall = [$control, $method];
                if (is_callable($toCall)) {
                    /** @var Control $control */
                    $control = call_user_func_array($toCall, $args);
                } else {
                    throw new Exception("Can't call method $method on class Control");
                }
            }
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            $exceptionWasThrownUponInit = true;
        }

        self::assertSame($expectExceptionUponInit, $exceptionWasThrownUponInit);
        if ($exceptionWasThrownUponInit) {
            return;
        }



        // Note: the actual level used is handled by the app/Exceptions/Handler.php
        // in Laravel, it's logged as error unless updated
        $expectExceptionToBeLogged
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $exceptionWasDetectedOutside = false;
        $returnValue = null;
        try {
            $returnValue = $control->execute();
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            if ($e === $theExceptionToThrow) {
                $exceptionWasDetectedOutside = true;
            }
        }



        self::assertSame(1, $closureRunCount);

        self::assertSame($expectCallbackToBeRun, $exceptionCallbackWasRun);
        for ($count = 0; $count < $exceptionCallbackCount; $count++) {
            $expected = $expectCallbackToBeRun
                ? 1
                : 0;
            self::assertSame($expected, $exceptionCallbackRunCount[$count]);
        }

        self::assertSame($expectExceptionThrownToCaller, $exceptionWasDetectedOutside);

        $expectedReturn = is_null($exceptionToTrigger)
            ? $intendedReturnValue
            : $defaultReturn;
        self::assertSame($expectedReturn, $returnValue);

        if ($exceptionToTrigger) {
            self::assertInstanceOf($exceptionToTrigger, $exception);
        } else {
            self::assertNull($exception);
        }
    }





    /**
     * DataProvider for test_that_control_method_calls_operate_properly().
     *
     * Provide the different combinations of how the Control object can be set up and called.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function controlMethodCallsDataProvider(): array
    {
        $catchCombinations = [
            null, // don't call
            [Throwable::class],
            [InvalidArgumentException::class],
            [DivisionByZeroError::class],
//            [[Throwable::class, DivisionByZeroError::class]],
//            [[Throwable::class, InvalidArgumentException::class]],
//            [Throwable::class, DivisionByZeroError::class],
//            [Throwable::class, InvalidArgumentException::class],
        ];

        $matchCombinations = [
            null, // don't call
            [self::$exceptionMessage],
            ['(NO MATCH)'],
        ];

        $matchRegexCombinations = [
            null, // don't call
            ['/Something/'],
            ['(NO MATCH)'],
        ];

        $callbackCombinations = [
            null, // don't call
            [true], // is replaced with the callback later, inside the test
        ];

        $knownCombinations = [
            null, // don't call
            ['ABC'],
//            [['ABC', 'DEF']],
        ];

        $channelCombinations = [
            null, // don't call
            ['stack'],
        ];

        $levelCombinations = [
            null, // don't call
            ['info'],
//            ['BLAH'], // error
        ];

        $reportCombinations = [
            null, // don't call
            [], // called with no arguments
        ];

        $rethrowCombinations = [
            null, // don't call
            [], // called with no arguments
        ];

        $suppressCombinations = [
            null, // don't call
            [], // called with no arguments
        ];

        $triggerExceptionTypes = [
            null, // don't throw an exception
            Exception::class,
            InvalidArgumentException::class,
        ];


        $return = [];

        foreach ($triggerExceptionTypes as $exceptionToTrigger) {

            self::$currentExceptionToTrigger = $exceptionToTrigger;

//          foreach ($channelCombinations as $channel) {
//          foreach ($levelCombinations as $level) {
//          foreach ($knownCombinations as $known) {
            foreach ($catchCombinations as $catch) {
                foreach ($matchCombinations as $match) {
                    foreach ($matchRegexCombinations as $matchRegex) {
                        foreach ($callbackCombinations as $callback) {
                            foreach ($reportCombinations as $report) {
                                foreach ($rethrowCombinations as $rethrow) {
                                    foreach ($suppressCombinations as $suppress) {

                                        $initMethodCalls = MethodCalls::new()
                                            ->add('catch', $catch)
                                            ->add('match', $match)
                                            ->add('matchRegex', $matchRegex)
                                            ->add('callback', $callback)
//                                            ->add('known', $known)
//                                            ->add('channel', $channel)
//                                            ->add('level', $level)
                                            ->add('report', $report)
//                                            ->add('dontReport', $dontReport)
                                            ->add('rethrow', $rethrow)
//                                            ->add('dontRethrow', $dontRethrow)
//                                            ->add('execute', $execute)
                                            ->add('suppress', $suppress)
                                        ;

                                        $return[] = self::buildParams($initMethodCalls);
                                    }
                                }
                            }
                        }
                    }
                }
            }



            $return[] = self::buildParams(MethodCalls::add('catch')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('catch', [Exception::class])
                ->add('catch', [Throwable::class, DivisionByZeroError::class])
                ->add('catch', [[Exception::class, DivisionByZeroError::class]]));



            $return[] = self::buildParams(MethodCalls::add('match')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('match', ['Blah1'])
                ->add('match', ['Blah2', 'Blah3'])
                ->add('match', [['Blah2', 'Blah4']]));

            $return[] = self::buildParams(MethodCalls::add('matchRegex')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('matchRegex', ['/^Blah1$/'])
                ->add('matchRegex', ['/^Blah2$/', '/^Blah3$/'])
                ->add('matchRegex', [['/^Blah2$/', '/^Blah4$/']]));



            $return[] = self::buildParams(MethodCalls::add('callback')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('callback', [true])
                ->add('callback', [true])
                ->add('callback', [true]));
            $return[] = self::buildParams(MethodCalls::add('callbacks')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('callbacks', [true])
                ->add('callbacks', [true, true])
                ->add('callbacks', [[true, true]])
                ->add('callback', [[true]]));

            foreach ([true, false] as $report) {
                foreach ([true, false] as $rethrow) {

                    $methodCalls = MethodCalls::add('callback', [true])
                        ->add('report', [$report])
                        ->add('rethrow', [$rethrow]);

                    $return[] = self::buildParams($methodCalls);
                }
            }



            $return[] = self::buildParams(MethodCalls::add('known')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('known', ['ABC']));
            $return[] = self::buildParams(MethodCalls::add('known', ['ABC', 'DEF']));
            $return[] = self::buildParams(MethodCalls::add('known', [['ABC', 'DEF']]));
            $return[] = self::buildParams(MethodCalls::add('known', [['ABC', 'DEF'], 'GHI']));
            $return[] = self::buildParams(MethodCalls
                ::add('known', ['ABC',])
                ->add('known', ['DEF'])
                ->add('known', ['ABC']));
            $return[] = self::buildParams(MethodCalls
                ::add('known', ['ABC',])
                ->add('known', ['DEF', 'GHI'])
                ->add('known', [['JKL', 'GHI']])
                ->add('known', [['JKL', 'GHI'], 'MNO']));



            $return[] = self::buildParams(MethodCalls::add('channel')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('channel', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels', ['a', 'b']));
            $return[] = self::buildParams(MethodCalls::add('channels', [['a', 'b']]));
            $return[] = self::buildParams(MethodCalls::add('channels', [['a', 'b'], 'c']));
            $return[] = self::buildParams(MethodCalls
                ::add('channel', ['a'])
                ->add('channel', ['b'])
                ->add('channel', ['a']));
            $return[] = self::buildParams(MethodCalls::add('channels')); // error - no params
            $return[] = self::buildParams(MethodCalls
                ::add('channels', ['a'])
                ->add('channels', ['b', 'c'])
                ->add('channels', [['d', 'c']])
                ->add('channels', [['d', 'c'], 'z']));



            $return[] = self::buildParams(MethodCalls::add('level')); // error - no params
            $return[] = self::buildParams(MethodCalls::add('level', ['BLAH'])); // error - invalid level
            $return[] = self::buildParams(MethodCalls
                ::add('level', [Settings::REPORTING_LEVEL_INFO])
                ->add('level', [Settings::REPORTING_LEVEL_WARNING])
                ->add('level', [Settings::REPORTING_LEVEL_INFO]));
            foreach (Settings::LOG_LEVELS as $level) {
                $return[] = self::buildParams(MethodCalls::add('level', [$level]));
            }



            foreach (Settings::LOG_LEVELS as $level) {
                $return[] = self::buildParams(MethodCalls::add($level));
            }



            $return[] = self::buildParams(MethodCalls::add('report'));
            $return[] = self::buildParams(MethodCalls::add('report', [true]));
            $return[] = self::buildParams(MethodCalls::add('report', [false]));
            $return[] = self::buildParams(MethodCalls::add('dontReport'));
            $return[] = self::buildParams(MethodCalls::add('report')->add('dontReport'));
            $return[] = self::buildParams(MethodCalls::add('report')->add('dontReport')->add('report'));



            $return[] = self::buildParams(MethodCalls::add('rethrow'));
            $return[] = self::buildParams(MethodCalls::add('rethrow', [true]));
            $return[] = self::buildParams(MethodCalls::add('rethrow', [false]));
            $return[] = self::buildParams(MethodCalls::add('rethrow', [fn(Throwable $exception) => true]));
            $return[] = self::buildParams(MethodCalls::add('dontRethrow'));
            $return[] = self::buildParams(MethodCalls::add('rethrow')->add('dontRethrow'));
            $return[] = self::buildParams(
                MethodCalls
                    ::add('rethrow', [fn(Throwable $exception) => true])
                    ->add('dontRethrow')
                    ->add('rethrow')
            );
            $return[] = self::buildParams(
                MethodCalls
                    ::add('rethrow', [fn(Throwable $exception) => true])
                    ->add('dontRethrow')
                    ->add('rethrow', [fn(Throwable $exception) => true])
            );



            $return[] = self::buildParams(
                MethodCalls
                    ::add('report')
                    ->add('rethrow')
                    ->add('suppress')
            );



            // no callbacks, don't report, but rethrow
            // causes the $context not to be built, and the exception re-thrown straight away
            $return[] = self::buildParams(MethodCalls::add('dontReport')->add('rethrow'));



            $return[] = self::buildParams(MethodCalls::add('default', ['abc']));



            // test more catch combinations
            $possibleCatchArgs = [
                Throwable::class,
                InvalidArgumentException::class,
                DivisionByZeroError::class,
                new CatchType(),
                CatchType::catch(Throwable::class),
                CatchType::catch(DivisionByZeroError::class),
            ];

            $catchCombinations2 = [];
            $catchCombinations2[] = null; // don't call
            foreach ($possibleCatchArgs as $catchArg1) {
                $catchCombinations2[] = [$catchArg1];
                foreach ($possibleCatchArgs as $catchArg2) {
                    if ($catchArg1 !== $catchArg2) {
                        $catchCombinations2[] = [$catchArg1, $catchArg2];
                    }
                }
            }

            foreach ($catchCombinations2 as $catch) {
                foreach ($matchCombinations as $match) {
                    $initMethodCalls = MethodCalls::add('catch', $catch)->add('match', $match);
                    $return[] = self::buildParams($initMethodCalls);
                }
            }
        }

        return $return;
    }

    /**
     * Determine the parameters to pass to the test_that_control_method_calls_operate_properly() test.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Control object.
     * @return array<string, mixed>
     */
    private static function buildParams(MethodCalls $initMethodCalls): array
    {

        $expectExceptionUponInit = self::willExceptionBeThrownUponInit($initMethodCalls);
        $fallbackCalls = self::buildFallbackCalls($initMethodCalls);

        $willBeCaughtBy = null;
        if (!$expectExceptionUponInit) {
            $catchTypes = self::pickCatchTypes($initMethodCalls);
            $willBeCaughtBy = self::determineWhatWillCatchTheException(
                self::$currentExceptionToTrigger,
                $fallbackCalls,
                $catchTypes
            );
        }

        return [
            'initMethodCalls' => $initMethodCalls,
            'exceptionToTrigger' => self::$currentExceptionToTrigger,
            'expectExceptionUponInit' => $expectExceptionUponInit,
            'expectCallbackToBeRun' => self::determineIfCallbacksWillBeRun(
                $willBeCaughtBy,
                $initMethodCalls,
            ),
            'expectExceptionToBeLogged' => self::determineIfExceptionWillBeLogged(
                $willBeCaughtBy,
                $fallbackCalls,
            ),
            'expectExceptionThrownToCaller' => self::willExceptionBeThrownToCaller(
                self::$currentExceptionToTrigger,
                $willBeCaughtBy,
                $fallbackCalls,
            ),
            'defaultReturn' => self::pickDefaultReturnValue($willBeCaughtBy, $initMethodCalls),
        ];
    }

    /**
     * Build the method calls that build the fallback object.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Control object.
     * @return MethodCalls
     */
    private static function buildFallbackCalls(MethodCalls $initMethodCalls): MethodCalls
    {
        $fallbackCalls = new MethodCalls();
        foreach ($initMethodCalls->getCalls() as $methodCall) {

            if ($methodCall->getMethod() == 'catch') {

                // use when not a CatchType
                $args = $methodCall->getArgsFlat(fn($a) => !$a instanceof CatchType);
                if (count($args)) {
                    $fallbackCalls->add($methodCall->getMethod(), $args);
                }

            } else {
                $fallbackCalls->add($methodCall->getMethod(), $methodCall->getArgs());
            }
        }

        return $fallbackCalls;
    }

    /**
     * Pick the already built CatchType objects from the initialisation calls.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Control object.
     * @return CatchType[]
     */
    private static function pickCatchTypes(MethodCalls $initMethodCalls): array
    {
        /** @var CatchType[] $args */
        $args = $initMethodCalls->getAllCallArgsFlat('catch', fn($arg) => $arg instanceof CatchType);
        return $args;
    }



    /**
     * Determine if a thrown exception will be caught.
     *
     * @param string|null $exceptionToTrigger The exception type to trigger (if any).
     * @param MethodCalls $fallbackCalls      The method calls that build the fallback object.
     * @param CatchType[] $catchTypes         The already built CatchType objects from the initialisation calls.
     * @return CatchType|MethodCalls|null
     */
    private static function determineWhatWillCatchTheException(
        ?string $exceptionToTrigger,
        MethodCalls $fallbackCalls,
        array $catchTypes
    ): CatchType|MethodCalls|null {

        if (is_null($exceptionToTrigger)) {
            return null;
        }



        // check each CatchType first
        foreach ($catchTypes as $catchType) {
            if (self::wouldCatchTypeCatch($exceptionToTrigger, $catchType, $fallbackCalls)) {
                return $catchType;
            }
        }

        // if there are CatchTypes, and the fall-back doesn't define class/es to catch, then stop
        if ((count($catchTypes)) && (!$fallbackCalls->hasCall('catch'))) {
            return null;
        }

        // check the fallback settings second
        /** @var string[] $fallbackCatchClasses */
        $fallbackCatchClasses = $fallbackCalls->getAllCallArgsFlat('catch');
        if (!self::checkIfExceptionClassesMatch($exceptionToTrigger, $fallbackCatchClasses)) {
            return null;
        }
        /** @var string[] $fallbackMatchStrings */
        $fallbackMatchStrings = $fallbackCalls->getAllCallArgsFlat('match');
        /** @var string[] $fallbackMatchRegexes */
        $fallbackMatchRegexes = $fallbackCalls->getAllCallArgsFlat('matchRegex');
        $a = self::checkIfMatchesMatch($fallbackMatchStrings);
        $b = self::checkIfRegexesMatch($fallbackMatchRegexes);

        if (($a === false || $b === false) && $a !== true && $b !== true) {
            return null;
        }
        return $fallbackCalls;
    }

    /**
     * Check if a given CatchType would catch an exception.
     *
     * @param string      $exceptionToTrigger The exception type to trigger (if any).
     * @param CatchType   $catchType          The CatchType to check.
     * @param MethodCalls $fallbackCalls      The method calls that build the fallback object.
     * @return boolean
     */
    private static function wouldCatchTypeCatch(
        string $exceptionToTrigger,
        CatchType $catchType,
        MethodCalls $fallbackCalls
    ): bool {

        $inspector = new Inspector($catchType);

        if (!self::checkIfExceptionClassesMatch($exceptionToTrigger, $inspector->getExceptionClasses())) {
            return false;
        }

        /** @var string[] $fallbackMatchStrings */
        $fallbackMatchStrings = $fallbackCalls->getAllCallArgsFlat('match');
        /** @var string[] $fallbackMatchRegexes */
        $fallbackMatchRegexes = $fallbackCalls->getAllCallArgsFlat('matchRegex');

        $matchStrings = $inspector->getRawMatchStrings() ?: $fallbackMatchStrings;
        $matchRegexes = $inspector->getRawMatchRegexes() ?: $fallbackMatchRegexes;

        $a = self::checkIfMatchesMatch($matchStrings);
        $b = self::checkIfRegexesMatch($matchRegexes);
        if (($a === false || $b === false) && $a !== true && $b !== true) {
            return false;
        }

        return true;
    }

    /**
     * Check if an array of exception classes match the exception type.
     *
     * @param string   $exceptionToTrigger The exception type that will be triggered.
     * @param string[] $exceptionClasses   The exception types to catch.
     * @return boolean
     */
    private static function checkIfExceptionClassesMatch(string $exceptionToTrigger, array $exceptionClasses): bool
    {
        if (!count($exceptionClasses)) {
            return true; // implies that all exceptions should be caught
        }
        if (in_array(Throwable::class, $exceptionClasses)) {
            return true;
        }
        if (in_array($exceptionToTrigger, $exceptionClasses)) {
            return true;
        }
        return false;
    }

    /**
     * Check if an array of match strings would match the exception message.
     *
     * @param string[] $matchStrings The matches to try.
     * @return boolean|null
     */
    private static function checkIfMatchesMatch(array $matchStrings): ?bool
    {
        if (!count($matchStrings)) {
            return null;
        }

        return in_array(self::$exceptionMessage, $matchStrings);
    }

    /**
     * Check if an array of regexes would match the exception message.
     *
     * @param string[] $matchRegexes The matches to try.
     * @return boolean|null
     */
    private static function checkIfRegexesMatch(array $matchRegexes): ?bool
    {
        if (!count($matchRegexes)) {
            return null;
        }

        foreach ($matchRegexes as $regex) {
            if (preg_match($regex, self::$exceptionMessage)) {
                return true;
            }
        }
        return false;
    }



    /**
     * Determine if an exception will be triggered when setting up the Control instance.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Control object.
     * @return boolean
     */
    private static function willExceptionBeThrownUponInit(MethodCalls $initMethodCalls): bool
    {
        // check the "level" arguments
        $fallbackLevels = collect($initMethodCalls->getCalls('level'))
            ->map(fn(MethodCall $m) => $m->getArgsFlat())
            ->flatten(1)
            ->toArray();
        /** @var string|null $lastFallbackLevel */
        $lastFallbackLevel = collect($fallbackLevels)->last();

        /** @var CatchType[] $catchTypes */
        $catchTypes = $initMethodCalls->getAllCallArgsFlat('catch', fn($arg) => $arg instanceof CatchType);
        $catchTypeLevels = collect($catchTypes)
            ->map(fn(CatchType $c) => new Inspector($c))
            ->map(fn(Inspector $c) => $c->getRawLevel() ?? $lastFallbackLevel)
            ->filter(fn(?string $level) => is_string($level))
            ->toArray();

        /** @var array<integer, string|null> $allLevels */
        $allLevels = array_merge($fallbackLevels, $catchTypeLevels);
        $allLevels = array_filter($allLevels, fn(?string $level) => !is_null($level)); // remove nulls

        foreach ($allLevels as $arg) {
            if (!in_array($arg, Settings::LOG_LEVELS)) {
                return true; // init error
            }
        }



        // check the number of parameters that the calls have
        $methodsAllowedToHaveNoParameters = [
            'report',
            'dontReport',
            'rethrow',
            'dontRethrow',
            'execute',
            'suppress',
            Settings::REPORTING_LEVEL_DEBUG,
            Settings::REPORTING_LEVEL_INFO,
            Settings::REPORTING_LEVEL_NOTICE,
            Settings::REPORTING_LEVEL_WARNING,
            Settings::REPORTING_LEVEL_ERROR,
            Settings::REPORTING_LEVEL_CRITICAL,
            Settings::REPORTING_LEVEL_ALERT,
            Settings::REPORTING_LEVEL_EMERGENCY,
        ];
        foreach ($initMethodCalls->getCalls() as $methodCall) {
            // allowed to be called with no parameters
            if (in_array($methodCall->getMethod(), $methodsAllowedToHaveNoParameters)) {
                continue;
            }
            // NOT allowed to be called without parameters
            if (!count($methodCall->getArgs())) {
                return true; // init error
            }
        }

        return false;
    }

    /**
     * Determine if an exception will be logged.
     *
     * @param CatchType|MethodCalls|null $willBeCaughtBy  The CatchType (or array of fallbackArgs) that catch the
     *                                                    exception.
     * @param MethodCalls                $initMethodCalls The method calls that build the fallback object.
     * @return boolean
     */
    private static function determineIfCallbacksWillBeRun(
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $initMethodCalls
    ): bool {

        if (!$willBeCaughtBy) {
            return false;
        }
        if (!$initMethodCalls->hasCall('callback')) {
            return false;
        }

        $report = true; // default value
        $rethrow = false; // default value
        $callsList = ['report', 'dontReport', 'rethrow', 'dontRethrow', 'suppress'];
        foreach ($initMethodCalls->getCalls($callsList) as $methodCall) {
            /** @var 'report'|'dontReport'|'rethrow'|'dontRethrow'|'suppress' $method */
            $method = $methodCall->getMethod();
            match ($method) {
                'report' => $report = (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => $report = false,
                'rethrow' => $rethrow = (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow' => $rethrow = false,
                'suppress' => $report = $rethrow = false,
            };
        }

        if ((!$report) && (!$rethrow)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if an exception will be logged.
     *
     * @param CatchType|MethodCalls|null $willBeCaughtBy The CatchType (or array of fallbackArgs) that catch the
     *                                                   exception.
     * @param MethodCalls                $fallbackCalls  The method calls that build the fallback object.
     * @return boolean
     */
    private static function determineIfExceptionWillBeLogged(
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $fallbackCalls,
    ): bool {

        if (!$willBeCaughtBy) {
            return false;
        }

        // what would the fall-back settings do
        $fallbackReport = null;
        foreach ($fallbackCalls->getCalls(['report', 'dontReport', 'suppress']) as $methodCall) {
            /** @var 'report'|'dontReport'|'suppress' $method */
            $method = $methodCall->getMethod();
            $fallbackReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport', 'suppress' => false,
            };
        }

        $defaultReport = true; // default true

        // if it's a CatchType that catches the exception
        if ($willBeCaughtBy instanceof CatchType) {
            $inspector = new Inspector($willBeCaughtBy);
            return $inspector->getRawReport() ?? $fallbackReport ?? $defaultReport;
        }

        // or if it's the fallback that catches the exception
        return $fallbackReport ?? $defaultReport;
    }

    /**
     * Determine if a thrown exception should be detected by the calling code.
     *
     * @param string|null                $exceptionToTrigger The exception type to trigger (if any).
     * @param CatchType|MethodCalls|null $willBeCaughtBy     The CatchType (or array of fallbackArgs) that catch the
     *                                                       exception.
     * @param MethodCalls                $fallbackCalls      The method calls that build the fallback object.
     * @return boolean
     */
    private static function willExceptionBeThrownToCaller(
        ?string $exceptionToTrigger,
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $fallbackCalls,
    ): bool {

        if (!$exceptionToTrigger) {
            return false;
        }

        if (!$willBeCaughtBy) {
            return true;
        }



        // what would the fall-back settings do
        $fallbackRethrow = null;
        foreach ($fallbackCalls->getCalls(['rethrow', 'dontRethrow', 'suppress']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow'|'suppress' $method */
            $method = $methodCall->getMethod();
            $fallbackRethrow = match ($method) {
                'rethrow' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontRethrow', 'suppress' => false,
            };
        }

        $defaultRethrow = false; // default false

        // if it's a CatchType that catches the exception
        if ($willBeCaughtBy instanceof CatchType) {
            $inspector = new Inspector($willBeCaughtBy);
            $return = $inspector->getRawRethrow() ?? $fallbackRethrow ?? $defaultRethrow;
            return is_callable($return) ? true : $return; // pretend that any callable will return true
        }

        // or if it's the fallback that catches the exception
        return $fallbackRethrow ?? $defaultRethrow;
    }

    /**
     * Determine the default return value that's used.
     *
     * @param CatchType|MethodCalls|null $willBeCaughtBy The CatchType (or array of fallbackArgs) that catch the
     *                                                   exception.
     * @param MethodCalls                $fallbackCalls  The method calls that build the fallback object.
     * @return mixed
     */
    private static function pickDefaultReturnValue(
        CatchType|MethodCalls|null $willBeCaughtBy,
        MethodCalls $fallbackCalls,
    ): mixed {

        // what would the fall-back settings do
        $fallbackDefault = null;
        foreach ($fallbackCalls->getCalls(['default']) as $methodCall) {
            $fallbackDefault = $methodCall->getArgs()[0] ?? null;
        }

        // if it's a CatchType that catches the exception
        if ($willBeCaughtBy instanceof CatchType) {
            $inspector = new Inspector($willBeCaughtBy);
            return $inspector->getRawDefault() ?? $fallbackDefault;
        }

        // or if it's the fallback that catches the exception
        return $fallbackDefault;
    }



    /**
     * Test that the Control object's methods set the log-levels properly.
     *
     * @test
     * @dataProvider logLevelDataProvider
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the Control object.
     * @param string      $expectedLevel   The log reporting level to expect.
     * @return void
     * @throws Exception When an initialisation method can't be called.
     */
    public static function test_the_log_levels(MethodCalls $initMethodCalls, string $expectedLevel): void
    {
        $callback = fn(Context $context) => self::assertSame($expectedLevel, $context->getLevel());

        // initialise the Control object
        $control = Control::prepare(self::throwExceptionClosure())
            ->callback($callback); // to inspect the log-level

        foreach ($initMethodCalls->getCalls() as $methodCall) {

            $method = $methodCall->getMethod();
            $args = $methodCall->getArgs();

            $toCall = [$control, $method];
            if (is_callable($toCall)) {
                /** @var Control $control */
                $control = call_user_func_array($toCall, $args);
            } else {
                throw new Exception("Can't call method $method on class Control");
            }
        }

        if (Environment::isLaravel()) {
            // the only way to actually change the log reporting level is to update app/Exceptions/Handler.php
            // otherwise it's reported as "error"
            self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR);
        } else {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        $control->execute();
    }

    /**
     * Provide data for the test_the_log_levels test.
     *
     * @return array<integer, array<string, MethodCalls|string>>
     */
    public static function logLevelDataProvider(): array
    {
        $return = [];

        // call ->level($logLevel)
        foreach (Settings::LOG_LEVELS as $logLevel) {
            $return[] = [
                'initMethodCalls' => MethodCalls::add('level', [$logLevel]),
                'expectedLevel' => $logLevel,
            ];
        }

        // call ->debug(), ->info(), …, ->emergency()
        foreach (Settings::LOG_LEVELS as $logLevel) {
            $return[] = [
                'initMethodCalls' => MethodCalls::add($logLevel),
                'expectedLevel' => $logLevel,
            ];
        }

        return $return;
    }



    /**
     * Test that the order the CatchTypes are defined in, matters.
     *
     * @test
     *
     * @return void
     * @throws Exception Exceptions that weren't supposed to be caught.
     */
    public static function test_that_the_catch_type_order_matters(): void
    {
        $callback1 = function () use (&$callback1Ran) {
            $callback1Ran = true;
        };
        $callback2 = function () use (&$callback2Ran) {
            $callback2Ran = true;
        };
        $callback3 = function () use (&$callback3Ran) {
            $callback3Ran = true;
        };

        $catchType1 = CatchType::catch(Exception::class)->callback($callback1);
        $catchType2 = CatchType::catch(Exception::class)->callback($callback2);
        $catchType3 = CatchType::catch(Exception::class)->callback($callback3);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Control::prepare(self::throwExceptionClosure())
            ->catch($catchType1)
            ->catch($catchType2)
            ->catch($catchType3)
            ->execute();

        self::assertTrue($callback1Ran);
        self::assertFalse($callback2Ran);
        self::assertFalse($callback3Ran);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Control::prepare(self::throwExceptionClosure())
            ->catch($catchType2)
            ->catch($catchType3)
            ->catch($catchType1)
            ->execute();

        self::assertFalse($callback1Ran);
        self::assertTrue($callback2Ran);
        self::assertFalse($callback3Ran);



        $callback1Ran = $callback2Ran = $callback3Ran = false;
        Control::prepare(self::throwExceptionClosure())
            ->catch($catchType3)
            ->catch($catchType1)
            ->catch($catchType2)
            ->execute();

        self::assertFalse($callback1Ran);
        self::assertFalse($callback2Ran);
        self::assertTrue($callback3Ran);
    }



    /**
     * Test that the correct parameters are passed to callbacks (via dependency injection).
     *
     * @test
     * @dataProvider callbackParameterDataProvider
     *
     * @param callable $callback                    The callback to run.
     * @param boolean  $expectExceptionToBeRethrown Whether to expect an exception or not.
     * @return void
     */
    public static function test_callback_parameters(callable $callback, bool $expectExceptionToBeRethrown): void
    {
        $caughtException = false;
        try {
            Control::prepare(self::throwExceptionClosure())
                ->callback($callback)
                ->execute();
        } catch (Throwable) {
            $caughtException = true;
        }

        self::assertSame($expectExceptionToBeRethrown, $caughtException);
    }

    /**
     * DataProvider for test_callback_parameters().
     *
     * @return array<integer, array<integer, callable|bool>>
     */
    public static function callbackParameterDataProvider(): array
    {
        // callbacks that don't cause an exception
        $callbacks = [];
        $callbacks[] = function ($exception) {
            self::assertInstanceOf(Exception::class, $exception);
        };

        $callbacks[] = function (Throwable $exception) {
            self::assertInstanceOf(Exception::class, $exception);
        };

        $callbacks[] = function ($e) {
            self::assertInstanceOf(Exception::class, $e);
        };

        $callbacks[] = function (Throwable $e) {
            self::assertInstanceOf(Exception::class, $e);
        };

        $callbacks[] = function (Context $a) {
            self::assertTrue(true); // $a will be a Context because of the parameter definition
        };

        $callbacks[] = function (Request $a) {
            self::assertTrue(true); // $a will be a Request because of the parameter definition
        };

        $callbacks = collect($callbacks)
            ->map(fn(callable $callback) => [$callback, false])
            ->values()
            ->toArray();



        // callbacks that cause an exception
        $exceptionCallbacks = [];
        $exceptionCallbacks[] = function ($a) {
        };
        $exceptionCallbacks[] = function (Throwable $throwable) {
        };

        $exceptionCallbacks = collect($exceptionCallbacks)
            ->map(fn(callable $callback) => [$callback, true])
            ->values()
            ->toArray();



        /** @var array<integer, array<integer, callable|bool>> $return */
        $return = array_merge($callbacks, $exceptionCallbacks);

        return $return;
    }



    /**
     * Test what happens when the callback alters the report and rethrow settings.
     *
     * @test
     * @dataProvider callbackContextEditDataProvider
     *
     * @param boolean $report   The report value the callback should set.
     * @param boolean $rethrow  The rethrow value the callback should set.
     * @param boolean $suppress The value for the callback to return.
     * @return void
     */
    public static function test_callback_that_updates_the_context_object(
        bool $report,
        bool $rethrow,
        bool $suppress,
    ): void {

        $callback1Ran = false;
        $callback1 = function (Context $context) use ($report, $rethrow, $suppress, &$callback1Ran) {
            $context->setReport($report);
            $context->setRethrow($rethrow);
            if ($suppress) {
                $context->suppress();
            }
            $callback1Ran = true;
        };

        $callback2Ran = false;
        $callback2 = function () use (&$callback2Ran) {
            $callback2Ran = true;
        };



        $report && !$suppress
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $exceptionWasRethrown = false;
        try {
            Control::prepare(self::throwExceptionClosure())
            ->callback($callback1)
            ->callback($callback2)
            ->execute();
        } catch (Throwable) {
            $exceptionWasRethrown = true;
        }



        self::assertSame($rethrow && !$suppress, $exceptionWasRethrown);
        self::assertTrue($callback1Ran);
        self::assertSame(($report || $rethrow) && !$suppress, $callback2Ran);
    }

    /**
     * Provide data for the test_callback_that_updates_the_context_object test.
     *
     * @return array<integer, array<string, boolean|null>>
     */
    public static function callbackContextEditDataProvider(): array
    {
        return [
            ['report' => false, 'rethrow' => false, 'suppress' => false],
            ['report' => true,  'rethrow' => false, 'suppress' => false],
            ['report' => false, 'rethrow' => true,  'suppress' => false],
            ['report' => true,  'rethrow' => true,  'suppress' => false],
            ['report' => false, 'rethrow' => false, 'suppress' => true],
            ['report' => true,  'rethrow' => false, 'suppress' => true],
            ['report' => false, 'rethrow' => true,  'suppress' => true],
            ['report' => true,  'rethrow' => true,  'suppress' => true],
        ];
    }



    /**
     * Test that global callbacks are called.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_global_callbacks_are_called(): void
    {
        $order = [];

        $globalCallback1 = function () use (&$order) {
            $order[] = 'gc1';
        };
        $globalCallback2 = function () use (&$order) {
            $order[] = 'gc2';
        };
        $globalCallback3 = function () use (&$order) {
            $order[] = 'gc3';
        };
        $globalCallback4 = function () use (&$order) {
            $order[] = 'gc4';
        };

        $callback1 = function () use (&$order) {
            $order[] = 'c1';
        };
        $callback2 = function () use (&$order) {
            $order[] = 'c2';
        };
        $callback3 = function () use (&$order) {
            $order[] = 'c3';
        };
        $callback4 = function () use (&$order) {
            $order[] = 'c4';
        };



        Control::globalCallback($globalCallback1);
        Control::prepare(self::throwExceptionClosure())
            ->callback($callback1)
            ->execute();

        Control::globalCallbacks($globalCallback2);
        Control::prepare(self::throwExceptionClosure())
            ->callback($callback2)
            ->execute();

        Control::globalCallbacks([$globalCallback3], $globalCallback4);
        Control::prepare(self::throwExceptionClosure())
            ->callback($callback3)
            ->callback($callback4)
            ->execute();

        self::assertSame(['gc1', 'c1', 'gc1', 'gc2', 'c2', 'gc1', 'gc2', 'gc3', 'gc4', 'c3', 'c4'], $order);
    }



    /**
     * Test that callbacks aren't called when they're not supposed to be.
     *
     * @test
     * @dataProvider callbacksArentRunDataProvider
     *
     * @param boolean $report               Whether to report or not.
     * @param boolean $rethrow              Whether to rethrow or not.
     * @param boolean $expectCallbacksToRun Whether the callbacks should be run or not.
     * @return void
     */
    public static function test_that_callbacks_arent_run(bool $report, bool $rethrow, bool $expectCallbacksToRun): void
    {
        $order = [];
        $callback1 = function () use (&$order) {
            $order[] = 1;
        };
        $callback2 = function () use (&$order) {
            $order[] = 2;
        };

        Control::globalCallback($callback1);

        try {
            Control::prepare(self::throwExceptionClosure())
                ->callback($callback2)
                ->report($report)
                ->rethrow($rethrow)
                ->execute();
        } catch (Throwable) {
        }

        if ($expectCallbacksToRun) {
            self::assertSame([1, 2], $order);
        } else {
            self::assertSame([], $order);
        }
    }

    /**
     * DataProvider for test_that_callbacks_arent_run.
     *
     * @return array<integer, array<string, boolean>>
     */
    public static function callbacksArentRunDataProvider(): array
    {
        $return = [];
        foreach ([true, false] as $report) {
            foreach ([true, false] as $rethrow) {
                $return[] = [
                    'report' => $report,
                    'rethrow' => $rethrow,
                    'expectCallbacksToRun' => $report || $rethrow,
                ];
            }
        }

        return $return;
    }



    /**
     * Test that the finally callable is called properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_finally(): void
    {
        $noException = fn() => null;
        $throwsException = fn() => throw new Exception('test');

        $finally = function () use (&$finallyWasCalled) {
            $finallyWasCalled = true;
        };
        $finallyFromCatchType = function () use (&$catchTypeFinallyWasCalled) {
            $catchTypeFinallyWasCalled = true;
        };

        $catchTypeNoFinally = new CatchType();
        $catchTypeWithFinally = CatchType::finally($finallyFromCatchType);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::run($noException, 'default', $finally);
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::run($throwsException, 'default', $finally);
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($noException)->finally($finally)->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($throwsException)->finally($finally)->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($noException, 'default', $finally)->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($throwsException, 'default', $finally)->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        // with a CatchType

        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($noException)
            ->catch($catchTypeNoFinally)
            ->finally($finally)
            ->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($throwsException)
            ->catch($catchTypeWithFinally)
            ->finally($finally)
            ->execute();
        self::assertSame(false, $finallyWasCalled);
        self::assertSame(true, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($noException, 'default', $finally)
            ->catch($catchTypeNoFinally)
            ->execute();
        self::assertSame(true, $finallyWasCalled);
        self::assertSame(false, $catchTypeFinallyWasCalled);



        $finallyWasCalled = $catchTypeFinallyWasCalled = false;
        Control::prepare($throwsException, 'default', $finally)
            ->catch($catchTypeWithFinally)
            ->execute();
        self::assertSame(false, $finallyWasCalled);
        self::assertSame(true, $catchTypeFinallyWasCalled);
    }



    /**
     * Test that closure is run using dependency injection.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_closure_is_called_using_dependency_injection(): void
    {
        $closure = fn(Request $request) => self::assertInstanceOf(Request::class, $request);
        Control::run($closure);
    }



    /**
     * Test that the "finally" callback is run using dependency injection.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_finally_is_called_using_dependency_injection(): void
    {
        $closure = fn(Request $request) => true;
        $finally = fn(Request $request) => self::assertInstanceOf(Request::class, $request);
        Control::run($closure, null, $finally);
    }



    /**
     * Test that the default values are set and returned properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_default_values_and_catch_types(): void
    {
        $throwException = self::throwExceptionClosure();

        // no default
        $return = Control::run($throwException);
        self::assertNull($return);

        // default
        $return = Control::prepare($throwException)
            ->default('control-default')
            ->execute();
        self::assertSame('control-default', $return);

        // with CatchType (that has a default)
        $return = Control::prepare($throwException)
            ->catch(CatchType::default('catch-type-default'))
            ->execute();
        self::assertSame('catch-type-default', $return);

        // with top-level default and a CatchType (that catches the exception and has a default)
        $return = Control::prepare($throwException)
            ->catch(CatchType::default('catch-type-default'))
            ->default('control-default')
            ->execute();
        self::assertSame('catch-type-default', $return);

        // with top-level default and a CatchType (that doesn't catch the exception)
        $return = Control::prepare($throwException)
            ->catch(new CatchType())
            ->default('control-default')
            ->execute();
        self::assertSame('control-default', $return);

        // no exception
        $return = Control::prepare(fn() => 'success', $return)
            ->default('control-default')
            ->execute();
        self::assertSame('success', $return);

        // callable default
        $return = Control::prepare($throwException)
            ->default(fn() => 'callable-default') // check that a callable default value is executed
            ->execute();
        self::assertSame('callable-default', $return);

        // with a callback that changes the default
        $return = Control::prepare($throwException)
            ->default('control-default')
            ->callback(fn(Context $context) => $context->setDefault('callback-default'))
            ->execute();
        self::assertSame('callback-default', $return);

        // with a callback that changes the default to a callable
        $return = Control::prepare($throwException)
            ->default('control-default')
            ->callback(fn(Context $context) => $context->setDefault(fn() => 'callback-default'))
            ->execute();
        self::assertSame('callback-default', $return);

        // with a callback that doesn't change the default
        $return = Control::prepare($throwException)
            ->default('control-default')
            ->callback(fn(Context $context) => true)
            ->execute();
        self::assertSame('control-default', $return);
    }



    /**
     * Test that the default value is used.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_different_ways_the_default_value_can_be_set(): void
    {
        $default = mt_rand();
        $return = Control::run(self::throwExceptionClosure(), $default);
        self::assertSame($default, $return);

        $default = mt_rand();
        $return = Control::prepare(self::throwExceptionClosure(), $default)->execute();
        self::assertSame($default, $return);

        $default = mt_rand();
        $return = Control::prepare(self::throwExceptionClosure())->default($default)->execute();
        self::assertSame($default, $return);

        $default1 = mt_rand();
        $default2 = mt_rand();
        $return = Control::prepare(self::throwExceptionClosure(), $default1)->default($default2)->execute();
        self::assertSame($default2, $return);
    }



    /**
     * Test that calling execute returns the same value each time.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_execute_runs_the_callable_each_time(): void
    {
        $runCount = 0;
        $closure = function () use (&$runCount) {
            $runCount++;
            return 'abc';
        };
        $control = Control::prepare($closure);

        self::assertSame('abc', $control->execute());
        self::assertSame(1, $runCount);

        self::assertSame('abc', $control->execute());
        self::assertSame(2, $runCount);
    }



    /**
     * Test that nested Control objects are captured when the inner one rethrows the exception.
     *
     * @test
     *
     * @return void
     */
    public static function test_nested_control_objects(): void
    {
        $line = __LINE__;
        $inspectContext = function (Context $context) use ($line) {

            $callMetaObjects = $context->getCallstack()->getMeta(CallMeta::class);

            // that it has 2 meta objects
            self::assertSame(2, count($callMetaObjects));

            // and that the first meta object…
            self::assertSame(__FILE__, $callMetaObjects[0]->getFile());
            self::assertSame($line + 23, $callMetaObjects[0]->getLine());

            // is different to the second meta object
            self::assertSame(__FILE__, $callMetaObjects[1]->getFile());
            self::assertSame($line + 19, $callMetaObjects[1]->getLine());
        };

        $closure1 = fn() => Control::prepare(self::throwExceptionClosure())
            ->rethrow()
            ->execute();

        Control::prepare($closure1)
            ->callback($inspectContext)
            ->execute();
    }



    /**
     * Test that the "known" values are detected properly from nested executions.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_known_values_of_nested_executions_work(): void
    {
        $inspectContext = function (Context $context) {

            $callStack = $context->getCallStack();

            // collect the "known" details from each frame's Meta objects
            $allKnown = [];
            /** @var Frame $frame */
            foreach ($callStack as $frame) {
                /** @var CallMeta $meta */
                foreach ($frame->getMeta(CallMeta::class) as $meta) {
                    $allKnown = array_merge($allKnown, $meta->getKnown());
                }
            }

            // compare them to the "known" details added to the Context object directly
            self::assertSame($allKnown, $context->getKnown());
            self::assertSame((bool) count($allKnown), $context->hasKnown());

            self::assertSame(2, count($allKnown));
            self::assertSame('known 1', $allKnown[0]);
            self::assertSame('known 2', $allKnown[1]);

            // check the "known" details by obtaining the CallMeta objects directly
            /** @var CallMeta[] $meta */
            $meta = $callStack->getMeta(CallMeta::class);
            self::assertSame(['known 1'], $meta[1]->getKnown());
            self::assertSame(['known 2'], $meta[2]->getKnown());
        };

        $closure2 = fn() => Control::prepare(self::throwExceptionClosure())
            ->known('known 2')
            ->rethrow()
            ->execute();

        $closure1 = fn() => Control::prepare($closure2)
            ->known('known 1')
            ->callback($inspectContext)
            ->execute();

        Clarity::context('context');

        Control::prepare($closure1)
            ->known('known-root')
            ->rethrow()
            ->execute();
    }



    /**
     * Test retrieval of the Context object.
     *
     * @test
     *
     * @return void
     */
    public function test_get_content(): void
    {
        $callback = function ($e, Context $context) {
            self::assertInstanceOf(Context::class, Clarity::getExceptionContext($e));
            self::assertSame($context, Clarity::getExceptionContext($e));

            self::assertInstanceOf(Context::class, ContextAPI::getLatestExceptionContext());
            self::assertSame($context, ContextAPI::getLatestExceptionContext());

            $e2 = new Exception('test');
            $newContext = Clarity::getExceptionContext($e2);
            self::assertInstanceOf(Context::class, $newContext);
            self::assertNotSame($context, $newContext);
        };

        Control::prepare(self::throwExceptionClosure())
            ->callback($callback)
            ->execute();
    }



    /**
     * Test that the prepare() and then execute() method calls work.
     *
     * @test
     *
     * @return void
     */
    public static function test_prepare_then_execute_methods(): void
    {
        self::assertSame('a', Control::prepare(fn() => 'a')->execute());
    }



    /**
     * Test that the run method works.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_run_method(): void
    {
        self::assertSame('a', Control::run(fn() => 'a'));
    }



    /**
     * Test that initialisation exceptions are generated properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_init_exceptions(): void
    {
        // an invalid level is passed
        $exceptionWasThrown = false;
        try {
            Control::prepare(fn() => 'a')->level('BLAH');
        } catch (ClarityControlInitialisationException) {
            $exceptionWasThrown = true;
        }
        self::assertTrue($exceptionWasThrown);
    }



    /**
     * Test that a processed exception's context is forgotten after being processed.
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_that_exception_contexts_are_forgotten_after_being_processed1(): void
    {
        $origException = null;
        $origContext = null;
        $callback = function (Throwable $exception, Context $context) use (&$origException, &$origContext) {
            $origException = $exception;
            $origContext = $context;
            throw new Exception('Exception during callback'); // <<< exception occurs during processing
        };

        try {
            Control::prepare(self::throwExceptionClosure())
                ->callback($callback)
                ->rethrow()
                ->execute();
        } catch (Throwable $callbackException) {

            self::assertNotSame($callbackException, $origException);

            $origException
                ? self::assertNotSame($origContext, Clarity::getExceptionContext($origException))
                : throw new Exception('$origException was not populated');
        }
    }


    /**
     * Test that ->getException($e) still retrieves the exception, even when the exception has been suppressed.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_get_exception_retrieves_it_even_when_suppressed(): void
    {
        Control::prepare(self::throwExceptionClosure())
            ->getException($e)
            ->suppress()
            ->execute();

        self::assertInstanceOf(Throwable::class, $e);
    }


    /**
     * Test that Control doesn't interfere with Laravel's normal error reporting functionality.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_normal_report_functionality_isnt_interfered_with(): void
    {
        if (!Environment::isLaravel()) {
            self::markTestSkipped("This test only runs when using Laravel");
        }

        self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR);
        report(new Exception('test'));
    }



    /**
     * Test that things run properly when Clarity Context is disabled.
     *
     * @test
     * @dataProvider disableClarityContextDataProvider
     *
     * @param class-string|null $exceptionToTrigger            The exception type to trigger (if any).
     * @param boolean           $useCallback                   Pass a callback to Clarity.
     * @param boolean           $report                        Report the exception.
     * @param boolean           $rethrow                       Rethrow the exception.
     * @param boolean           $expectCallbackToBeRun         Except the exception callback to be run?.
     * @param boolean           $expectExceptionToBeLogged     Expect the exception to be logged?.
     * @param boolean           $expectExceptionThrownToCaller Except the exception to be thrown to the caller?.
     * @return void
     */
    public static function test_that_things_run_when_clarity_context_is_disabled(
        ?string $exceptionToTrigger,
        bool $useCallback,
        bool $report,
        bool $rethrow,
        bool $expectCallbackToBeRun,
        bool $expectExceptionToBeLogged,
        bool $expectExceptionThrownToCaller,
    ): void {

        Framework::config()->updateConfig([InternalSettings::LARAVEL_CONTEXT__CONFIG_NAME . '.enabled' => false]);

        // set up the closure to run
        $intendedReturnValue = mt_rand();
        $closureRunCount = 0;
        $closure = function () use (&$closureRunCount, $intendedReturnValue, $exceptionToTrigger) {
            $closureRunCount++;
            if (!is_null($exceptionToTrigger)) {
                /** @var Throwable $exception */
                $exception = new $exceptionToTrigger(self::$exceptionMessage);
                throw $exception;
            }
            return $intendedReturnValue;
        };



        $exceptionCallbackWasRun = false;
        $callback = function (Context $context, Throwable $e) use ($report, $rethrow, &$exceptionCallbackWasRun) {

            $callStack = $context->getCallStack();
            $trace = $context->getStackTrace();

            // no meta-objects will be collected when Clarity is disabled
            self::assertSame($e, $context->getException());
            self::assertSame(0, count($callStack->getMeta())); // doesn't track meta-data
            self::assertSame([], $context->getKnown()); // doesn't track "known"
            self::assertSame(false, $context->hasKnown()); // doesn't track "known"
            self::assertSame(['some-channel1', 'some-channel2'], $context->getChannels());
            self::assertSame(Settings::REPORTING_LEVEL_DEBUG, $context->getLevel());
            self::assertSame($report, $context->getReport());
            self::assertSame($rethrow ? $e : false, $context->getRethrow());

            self::assertTrue(count($callStack) > 0); // has frames
            self::assertNull($callStack->getLastApplicationFrameIndex());
            self::assertNull($callStack->getLastApplicationFrame());
            self::assertNull($callStack->getExceptionThrownFrameIndex());
            self::assertNull($callStack->getExceptionThrownFrame());
            self::assertNull($callStack->getExceptionCaughtFrameIndex());
            self::assertNull($callStack->getExceptionCaughtFrame());

            self::assertTrue(count($trace) > 0); // has frames
            self::assertNull($trace->getLastApplicationFrameIndex());
            self::assertNull($trace->getLastApplicationFrame());
            self::assertNull($trace->getExceptionThrownFrameIndex());
            self::assertNull($trace->getExceptionThrownFrame());
            self::assertNull($trace->getExceptionCaughtFrameIndex());
            self::assertNull($trace->getExceptionCaughtFrame());

            $exceptionCallbackWasRun = true;
        };



        $default = mt_rand();
        Clarity::context(['something']);
        $clarity = Control::prepare($closure)
            ->default($default)
            ->debug()
            ->channel('some-channel1')
            ->channels(['some-channel2'])
            ->known('known-1234')
            ->report($report)
            ->rethrow($rethrow)
            ->getException($exception);
        if ($useCallback) {
            $clarity->callback($callback);
        }



        // Note: the actual level used is handled by the app/Exceptions/Handler.php
        // in Laravel, it's logged as error unless updated
        $expectExceptionToBeLogged
            ? self::logShouldReceive(Settings::REPORTING_LEVEL_ERROR)
            : self::logShouldNotReceive(Settings::REPORTING_LEVEL_ERROR);



        // run the closure
        $exceptionWasDetectedOutside = false;
        $returnValue = null;
        try {
            $returnValue = $clarity->execute();
        } catch (Throwable $e) {
//            dump("Exception: \"{$e->getMessage()}\" in {$e->getFile()}:{$e->getLine()}");
            $exceptionWasDetectedOutside = true;
        }



        self::assertSame(1, $closureRunCount);
        self::assertSame($expectCallbackToBeRun, $exceptionCallbackWasRun);
        self::assertSame($expectExceptionThrownToCaller, $exceptionWasDetectedOutside);

        if (is_null($exceptionToTrigger)) {
            self::assertSame($intendedReturnValue, $returnValue);
        } else {
            $expectExceptionThrownToCaller
                ? self::assertNull($returnValue)
                : self::assertSame($default, $returnValue);
        }

        if ($exceptionToTrigger) {
            self::assertInstanceOf($exceptionToTrigger, $exception);
        } else {
            self::assertNull($exception);
        }
    }

    /**
     * DataProvider for test_that_things_run_when_clarity_context_is_disabled().
     *
     * @return array<integer, array<string, boolean|string|null>>
     */
    public static function disableClarityContextDataProvider(): array
    {
        $triggerExceptionTypes = [
            null, // don't throw an exception
            Exception::class,
            InvalidArgumentException::class,
        ];

        $return = [];

        foreach ($triggerExceptionTypes as $exceptionToTrigger) {
            foreach ([true, false] as $useCallback) {
                foreach ([true, false] as $report) {
                    foreach ([true, false] as $rethrow) {

                        $expectCallbackToBeRun = $exceptionToTrigger && $useCallback && ($report || $rethrow);
                        $expectExceptionToBeLogged = $exceptionToTrigger && $report;
                        $expectExceptionThrownToCaller = $exceptionToTrigger && $rethrow;

                        $return[] = [
                            'exceptionToTrigger' => $exceptionToTrigger,
                            'useCallback' => $useCallback,
                            'report' => $report,
                            'rethrow' => $rethrow,
                            'expectCallbackToBeRun' => $expectCallbackToBeRun,
                            'expectExceptionToBeLogged' => $expectExceptionToBeLogged,
                            'expectExceptionThrownToCaller' => $expectExceptionThrownToCaller,
                        ];
                    }
                }
            }
        }

        return $return;
    }





    /**
     * Test that the CallMeta object is inserted into the correct frame when running Control::run(..).
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_that_call_meta_is_inserted_into_the_correct_frame_when_running_run(): void
    {
        $callback = function (Context $context) {
            $meta = $context->getCallStack()->getMeta(CallMeta::class)[0];
            self::assertSame(__FILE__, $meta->getFile());
            self::assertSame(__LINE__ + 4, $meta->getLine());
        };
        Control::globalCallback($callback);

        Control::run(fn() => throw new Exception(self::$exceptionMessage));
    }

    /**
     * Test that the CallMeta object is inserted into the correct frame when running Control::prepare(..)->execute().
     *
     * @test
     *
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_that_call_meta_is_inserted_into_the_correct_frame_when_running_prepare_execute(): void
    {
        $callback = function (Context $context) {
            $meta = $context->getCallStack()->getMeta(CallMeta::class)[0];
            self::assertSame(__FILE__, $meta->getFile());
            self::assertSame(__LINE__ + 4, $meta->getLine());
        };
        Control::globalCallback($callback);

        Control::prepare(fn() => throw new Exception(self::$exceptionMessage))->execute();
    }



    /**
     * Test that Control calls can be nested.
     *
     * Also tests that Clarity Context can hold Context objects for more than one exception at a time.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_clarity_calls_can_be_nested()
    {
        $callback = function (Throwable $exception, Context $context1) {
            $exception1 = $exception;

            $callback2 = function (Throwable $exception, Context $context2) use ($exception1, $context1) {
                $exception2 = $exception;

                // if Clarity Context doesn't hold one of these context objects, it will build a new one based on the
                // exception
                // when that happens, the resulting context object won't be the same as the one it previously reported
                self::assertSame($context1, Clarity::getExceptionContext($exception1));
                self::assertSame($context2, Clarity::getExceptionContext($exception2));
            };

            Control::prepare(self::throwExceptionClosure())
                ->callback($callback2)
                ->execute();
        };

        Control::prepare(self::throwExceptionClosure())
            ->callback($callback)
            ->execute();
    }





    /**
     * Build a closure that throws a new exception.
     *
     * @return callable
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    private static function throwExceptionClosure(): callable
    {
        return fn() => throw new Exception(self::$exceptionMessage);
    }

    /**
     * Assert that the logger should be called once.
     *
     * @param string $level The log reporting level to check.
     * @return void
     * @throws Exception When the framework isn't recognised.
     */
    private static function logShouldReceive(string $level): void
    {
        if (!Environment::isLaravel()) {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        Log::shouldReceive($level)->once();
    }

    /**
     * Assert that the logger should not be called at all.
     *
     * @param string $level The log reporting level to check.
     * @return void
     * @throws Exception When the framework isn't recognised.
     */
    private static function logShouldNotReceive(string $level): void
    {
        if (!Environment::isLaravel()) {
            throw new Exception('Log checking needs to be updated for the current framework');
        }

        Log::shouldReceive($level)->atMost()->times(0);
    }





    /**
     * Test that the Control class resolves the exception to rethrow properly.
     *
     * @test
     * @dataProvider resolveExceptionToRethrowDataProvider
     *
     * @param Throwable                       $origException    The exception that "occurred".
     * @param boolean|callable|null           $catchTypeRethrow The rethrow value to pass when setting up Control Obj.
     * @param boolean|callable|Throwable|null $contextRethrow   The rethrow value to pass to Context obj in callback.
     * @param Throwable|null                  $expected         The expected exception to throw.
     * @param boolean                         $expectException  Whether an exception should be thrown.
     * @return void
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    public static function test_resolution_of_the_exception_to_throw(
        Throwable $origException,
        bool|callable|null $catchTypeRethrow,
        bool|callable|Throwable|null $contextRethrow,
        ?Throwable $expected,
        bool $expectException,
    ): void {

        $rethrownException = null;
        try {

            $callback = function (Context $context) use ($contextRethrow) {
                if (is_null($contextRethrow)) {
                    return;
                }
                $context->setRethrow($contextRethrow);
            };

            $control = Control::prepare(fn() => throw $origException)->callback($callback);
            if (!is_null($catchTypeRethrow)) {
                $control->rethrow($catchTypeRethrow);
            }
            $control->execute();

        } catch (Throwable $e) {
            $rethrownException = $e;
        }

        $expectException
            ? self::assertInstanceOf(ClarityControlRuntimeException::class, $rethrownException)
            : self::assertSame($expected, $rethrownException);
    }

    /**
     * DataProvider for test_resolution_of_which_exception_to_throw().
     *
     * @return array<array<string,mixed>>
     */
    public static function resolveExceptionToRethrowDataProvider(): array
    {
        $exception1 = new Exception();
        $exception2 = new Exception();

        $return = [];



        // null
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => null,
            'expected' => null,
            'expectException' => false,
        ];





        // CatchType based rethrow values - where the Control object (via its default CatchType)
        // is updated with the rethrow value

        // true
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => true,
            'contextRethrow' => null,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // false
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => false,
            'contextRethrow' => null,
            'expected' => null,
            'expectException' => false,
        ];



        // callable - returns null
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => null,
            'contextRethrow' => null,
            'expected' => null,
            'expectException' => false,
        ];

        // exception - returns false
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => false,
            'contextRethrow' => null,
            'expected' => null,
            'expectException' => false,
        ];

        // exception - returns true
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => true,
            'contextRethrow' => null,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // exception - returns the same exception
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => $exception1,
            'contextRethrow' => null,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // exception - returns a different exception
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => $exception2,
            'contextRethrow' => null,
            'expected' => $exception2,
            'expectException' => false,
        ];

        // exception - returns an invalid value
        // null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => fn() => 'invalid',
            'contextRethrow' => null,
            'expected' => null,
            'expectException' => true,
        ];





        // Context based rethrow values - where the Context object is updated with the rethrow value (via a callback)

        // null
        // false
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => false,
            'expected' => null,
            'expectException' => false,
        ];

        // null
        // true
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => true,
            'expected' => $exception1,
            'expectException' => false,
        ];



        // null
        // callable - returns null
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => null,
            'expected' => null,
            'expectException' => false,
        ];

        // null
        // callable - returns false
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => false,
            'expected' => null,
            'expectException' => false,
        ];

        // null
        // callable - returns true
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => true,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // null
        // callable - returns the same exception
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => $exception1,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // null
        // callable - returns a different exception
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => $exception2,
            'expected' => $exception2,
            'expectException' => false,
        ];

        // null
        // callable - returns an invalid value
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => fn() => 'invalid',
            'expected' => null,
            'expectException' => true,
        ];



        // null
        // exception - same one
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => $exception1,
            'expected' => $exception1,
            'expectException' => false,
        ];

        // null
        // exception - different one
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => null,
            'contextRethrow' => $exception2,
            'expected' => $exception2,
            'expectException' => false,
        ];





        // where the Control (via its default CatchType) and Context objects are BOTH updated with rethrow values
        // checks that the Context rethrow value overrides the CatchType one

        // true
        // false
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => true,
            'contextRethrow' => false,
            'expected' => null,
            'expectException' => false,
        ];

        // false
        // true
        $return[] = [
            'origException' => $exception1,
            'catchTypeRethrow' => false,
            'contextRethrow' => true,
            'expected' => $exception1,
            'expectException' => false,
        ];


        return $return;
    }
}
