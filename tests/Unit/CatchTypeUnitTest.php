<?php

namespace CodeDistortion\ClarityControl\Tests\Unit;

use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Support\Inspector;
use CodeDistortion\ClarityControl\Tests\PHPUnitTestCase;
use CodeDistortion\ClarityControl\Tests\Support\MethodCall;
use CodeDistortion\ClarityControl\Tests\Support\MethodCalls;
use DivisionByZeroError;
use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Test the CatchType class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class CatchTypeUnitTest extends PHPUnitTestCase
{
    /**
     * Test that CatchType operates properly with the given combinations of ways it can be called.
     *
     * @test
     * @dataProvider catchTypeDataProvider
     *
     * @param MethodCalls           $initMethodCalls          Methods to call when initialising the CatchType object.
     * @param boolean               $expectExceptionUponInit  Expect exception thrown when initialising.
     * @param string[]              $expectedExceptionClasses The expected exception classes.
     * @param string[]              $expectedMatchStrings     The expected string matches.
     * @param string[]              $expectedMatchRegexes     The expected regex matches.
     * @param string[]              $expectedKnownStrings     The expected known issues.
     * @param string[]              $expectedChannels         The expected channels.
     * @param string|null           $expectedLevel            The expected log level.
     * @param boolean|null          $expectedReport           The expected report setting.
     * @param boolean|callable|null $expectedRethrow          The expected rethrow setting.
     * @param mixed                 $expectedDefault          The expected default value.
     * @param callable|null         $expectedFinally          The expected finally value.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    public static function test_that_catchtype_operates_properly(
        MethodCalls $initMethodCalls,
        bool $expectExceptionUponInit,
        array $expectedExceptionClasses,
        array $expectedMatchStrings,
        array $expectedMatchRegexes,
        array $expectedKnownStrings,
        array $expectedChannels,
        ?string $expectedLevel,
        ?bool $expectedReport,
        bool|callable|null $expectedRethrow,
        mixed $expectedDefault,
        ?callable $expectedFinally,
    ): void {

        // initialise the CatchType object
        $exceptionWasThrownUponInit = false;
        $exceptionCallbacks = [];
        try {
            $catchTypeObject = null;
            foreach ($initMethodCalls->getCalls() as $methodCall) {

                $method = $methodCall->getMethod();
                $args = $methodCall->getArgs();

                // place the exception callback into the args for calls to callback() / callbacks()
                foreach ($args as $index => $arg) {
                    if ((in_array($method, ['callback', 'callbacks'])) && ($arg ?? null)) {
                        $args[$index] = $exceptionCallbacks[] = fn() => 'Hello';
                    }
                }

                $toCall = [$catchTypeObject ?? CatchType::class, $method];
                if (is_callable($toCall)) {
                    /** @var CatchType $catchTypeObject */
                    $catchTypeObject = call_user_func_array($toCall, $args);
                } else {
                    throw new Exception("Can't call method $method on class CatchType");
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

        if (is_null($catchTypeObject)) {
            return;
        }

        $inspector = new Inspector($catchTypeObject);
        self::assertSame($expectedExceptionClasses, $inspector->getRawExceptionClasses());
        self::assertSame($expectedMatchStrings, $inspector->getRawMatchStrings());
        self::assertSame($expectedMatchRegexes, $inspector->getRawMatchRegexes());

        if (($initMethodCalls->hasCall('callback')) || ($initMethodCalls->hasCall('callbacks'))) {
            foreach ($inspector->getRawCallbacks() as $callback) {
                self::assertSame(array_shift($exceptionCallbacks), $callback);
            }
        }

        self::assertSame($expectedKnownStrings, $inspector->getRawKnown());
        self::assertSame($expectedChannels, $inspector->getRawChannels());
        self::assertSame($expectedLevel, $inspector->getRawLevel());
        self::assertSame($expectedReport, $inspector->getRawReport());
        self::assertSame($expectedRethrow, $inspector->getRawRethrow());
        self::assertSame($expectedDefault, $inspector->getRawDefault());
        self::assertSame($expectedFinally, $inspector->getFinally());
    }

    /**
     * DataProvider for test_that_catchtype_operates_properly().
     *
     * Provide the different combinations of how the CatchType object can be set up and called.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function catchTypeDataProvider(): array
    {
        $catchCombinations = [
            null, // don't call
            [Throwable::class],
            [InvalidArgumentException::class],
            [DivisionByZeroError::class],
        ];

        $matchCombinations = [
            null, // don't call
            ['Something happened'],
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
            ['ABC-123'],
//            [['ABC-123', 'DEF-456']],
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

        $finallyCombinations = [
            null, // don't call
            [], // called with no arguments
            [fn() => true],
        ];



        $return = [];

        foreach ($catchCombinations as $catch) {
            foreach ($matchCombinations as $match) {
                foreach ($matchRegexCombinations as $matchRegex) {
                    foreach ($callbackCombinations as $callback) {
                        foreach ($knownCombinations as $known) {
                            foreach ($channelCombinations as $channel) {
                                foreach ($levelCombinations as $level) {
                                    foreach ($reportCombinations as $report) {
                                        foreach ($rethrowCombinations as $rethrow) {
                                            foreach ($finallyCombinations as $finally) {

                                                $initMethodCalls = MethodCalls::new()
                                                    ->add('catch', $catch)
                                                    ->add('match', $match)
                                                    ->add('matchRegex', $matchRegex)
                                                    ->add('callback', $callback)
                                                    ->add('known', $known)
                                                    ->add('channel', $channel)
                                                    ->add('level', $level)
                                                    ->add('report', $report)
//                                                    ->add('dontReport', $dontReport)
                                                    ->add('rethrow', $rethrow)
//                                                    ->add('dontRethrow', $dontRethrow)
                                                    ->add('finally', $finally)
//                                                    ->add('execute', $execute)
                                                ;

                                                $return[] = self::buildParams($initMethodCalls);
                                            }
                                        }
                                    }
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



        $return[] = self::buildParams(MethodCalls::add('known')); // error - no params
        $return[] = self::buildParams(MethodCalls
            ::add('known', ['ABC',])
            ->add('known', ['GHI', 'XYZ'])
            ->add('known', [['DEF', 'XYZ']]));



        $return[] = self::buildParams(MethodCalls::add('channel')); // error - no params
        $return[] = self::buildParams(MethodCalls
            ::add('channel', ['a'])
            ->add('channel', ['c'])
            ->add('channel', ['b']));
        $return[] = self::buildParams(MethodCalls::add('channels')); // error - no params
        $return[] = self::buildParams(MethodCalls
            ::add('channels', ['a'])
            ->add('channels', ['b', 'c'])
            ->add('channels', [['b', 'd']])
            ->add('channel', ['z']));



        $return[] = self::buildParams(MethodCalls::add('level')); // error - no params
        $return[] = self::buildParams(MethodCalls::add('level', ['BLAH'])); // error - invalid level
        $return[] = self::buildParams(MethodCalls
            ::add('level', [Settings::REPORTING_LEVEL_INFO])
            ->add('level', [Settings::REPORTING_LEVEL_WARNING])
            ->add('level', [Settings::REPORTING_LEVEL_INFO]));

        foreach (Settings::LOG_LEVELS as $level) {
            $return[] = self::buildParams(MethodCalls::add($level));
        }

        $return[] = self::buildParams(
            MethodCalls
                ::add(Settings::REPORTING_LEVEL_DEBUG)
                ->add(Settings::REPORTING_LEVEL_EMERGENCY)
        );

        $return[] = self::buildParams(
            MethodCalls
                ::add(Settings::REPORTING_LEVEL_DEBUG)
                ->add('level', [Settings::REPORTING_LEVEL_WARNING])
        );

        $return[] = self::buildParams(
            MethodCalls
                ::add(Settings::REPORTING_LEVEL_DEBUG)
                ->add('level', [Settings::REPORTING_LEVEL_WARNING])
                ->add(Settings::REPORTING_LEVEL_INFO)
        );



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



        $return[] = self::buildParams(MethodCalls::add('default')); // error - no params
        $return[] = self::buildParams(MethodCalls::add('default', ['something']));

        return $return;
    }



    /**
     * Determine the parameters to pass to the test_that_catchtype_operates_properly test.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @return array<string, mixed>
     */
    private static function buildParams(MethodCalls $initMethodCalls): array
    {
        $expectExceptionUponInit = self::willExceptionBeThrownUponInit($initMethodCalls);

        $exceptionTypes = [];
        $matchStrings = [];
        $matchRegexs = [];
        $knownStrings = [];
        $channels = [];
        $report = null;
        $rethrow = null;
        $default = null;
        $finally = null;

        $avu = fn(array $array) => array_values(array_unique($array));

        if (!$expectExceptionUponInit) {

            foreach ($initMethodCalls->getCalls() as $methodCall) {
                match ($methodCall->getMethod()) {
                    'catch' => $exceptionTypes = $avu([...$exceptionTypes, ...$methodCall->getArgsFlat()]),
                    'match' => $matchStrings = $avu([...$matchStrings, ...$methodCall->getArgsFlat()]),
                    'matchRegex' => $matchRegexs = $avu([...$matchRegexs, ...$methodCall->getArgsFlat()]),
                    'known' => $knownStrings = $avu([...$knownStrings, ...$methodCall->getArgsFlat()]),
                    'channel',
                    'channels' => $channels = $avu([...$channels, ...$methodCall->getArgsFlat()]),
                    'report' => $report = $methodCall->getArgs()[0] ?? true,
                    'dontReport' => $report = false,
                    'rethrow' => $rethrow = $methodCall->getArgs()[0] ?? true,
                    'dontRethrow' => $rethrow = false,
                    'suppress' => $report = $rethrow = false,
                    'default' => $default = $methodCall->getArgs()[0] ?? null,
                    'finally' => $finally = $methodCall->getArgs()[0] ?? null,
                    default => null,
                };
            }
        }

        return [
            'initMethodCalls' => $initMethodCalls,
            'expectExceptionUponInit' => $expectExceptionUponInit,
            'expectedExceptionClasses' => $exceptionTypes,
            'expectedMatchStrings' => $matchStrings,
            'expectedMatchRegexes' => $matchRegexs,
            'expectedKnownStrings' => $knownStrings,
            'expectedChannels' => $channels,
            'expectedLevel' => self::pickLevel($initMethodCalls),
            'expectedReport' => $report,
            'expectedRethrow' => $rethrow,
            'expectedDefault' => $default,
            'expectedFinally' => $finally,
        ];
    }



    /**
     * Determine if an exception will be triggered when setting up the CatchType instance.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @return boolean
     */
    private static function willExceptionBeThrownUponInit(MethodCalls $initMethodCalls): bool
    {
        $methodsAllowedToHaveNoParameters = [
            'report',
            'dontReport',
            'rethrow',
            'dontRethrow',
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

        // check the "level" arguments
        $allLevels = collect($initMethodCalls->getCalls('level'))
            ->map(fn(MethodCall $m) => $m->getArgsFlat())
            ->flatten(1)
            ->toArray();

        foreach ($allLevels as $arg) {
            if (!in_array($arg, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
                return true; // init error
            }
        }

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
     * Determine what the log reporting level should be set to.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @return string|null
     */
    private static function pickLevel(MethodCalls $initMethodCalls): ?string
    {
        $level = null;
        foreach ($initMethodCalls->getCalls() as $methodCall) {
            match ($methodCall->getMethod()) {
                'level' => $level = $methodCall->getArgs()[0] ?? null,
                Settings::REPORTING_LEVEL_DEBUG => $level = Settings::REPORTING_LEVEL_DEBUG,
                Settings::REPORTING_LEVEL_INFO => $level = Settings::REPORTING_LEVEL_INFO,
                Settings::REPORTING_LEVEL_NOTICE => $level = Settings::REPORTING_LEVEL_NOTICE,
                Settings::REPORTING_LEVEL_WARNING => $level = Settings::REPORTING_LEVEL_WARNING,
                Settings::REPORTING_LEVEL_ERROR => $level = Settings::REPORTING_LEVEL_ERROR,
                Settings::REPORTING_LEVEL_CRITICAL => $level = Settings::REPORTING_LEVEL_CRITICAL,
                Settings::REPORTING_LEVEL_ALERT => $level = Settings::REPORTING_LEVEL_ALERT,
                Settings::REPORTING_LEVEL_EMERGENCY => $level = Settings::REPORTING_LEVEL_EMERGENCY,
                default => null
            };
        }

        /** @var string|null $level  */
        return $level;
    }
}
