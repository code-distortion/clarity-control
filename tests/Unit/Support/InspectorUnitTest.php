<?php

namespace CodeDistortion\ClarityControl\Tests\Unit\Support;

use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Support\Inspector;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use CodeDistortion\ClarityControl\Tests\Support\MethodCalls;
use DivisionByZeroError;
use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Test the Inspector class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class InspectorUnitTest extends LaravelTestCase
{
    /** @var string The message to use when throwing exceptions. */
    private static string $exceptionMessage = 'Something happened';



    /**
     * Test that CatchType operates properly with the given combinations of ways it can be called.
     *
     * @test
     * @dataProvider inspectorDataProvider
     *
     * @param MethodCalls      $initMethodCalls             Methods to call when initialising the CatchType object.
     * @param MethodCalls      $fallbackCalls               Methods to call when initialising the fallback CatchType
     *                                                      object.
     * @param string|null      $exceptionTypeToTrigger      The exception type to trigger (if any).
     * @param boolean|null     $expectedCheckForMatch       The expected outcome from checkForMatch.
     * @param string[]         $expectedGetExceptionClasses The expected exception classes.
     * @param boolean          $expectedUseCallback         Whether to expect the CatchType's callback to be used or
     *                                                      not.
     * @param boolean          $expectedUseFallbackCallback Whether to expect the fallback CatchType's callback to be
     *                                                      used or not.
     * @param string[]         $expectedGetKnown            The expected known issues.
     * @param string[]         $expectedGetChannels         The expected channels.
     * @param string|null      $expectedGetLevel            The expected level.
     * @param boolean|null     $expectedShouldReport        The expected should-report.
     * @param boolean|callable $expectedRethrow             The expected should-rethrow.
     * @param mixed            $expectedDefault             The expected default value.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    public static function test_that_inspector_operates_properly(
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
        ?string $exceptionTypeToTrigger,
        ?bool $expectedCheckForMatch,
        array $expectedGetExceptionClasses,
        bool $expectedUseCallback,
        bool $expectedUseFallbackCallback,
        array $expectedGetKnown,
        array $expectedGetChannels,
        ?string $expectedGetLevel,
        ?bool $expectedShouldReport,
        bool|callable $expectedRethrow,
        mixed $expectedDefault,
    ): void {

        $fallbackExceptionCallback = fn() => 'hello';
        $exceptionCallback = fn() => 'hello';

        $fallbackCatchType = self::buildCatchType($fallbackCalls, $fallbackExceptionCallback);
        $catchType = self::buildCatchType($initMethodCalls, $exceptionCallback);

        /** @var Throwable|null $exception */
        $exception = $exceptionTypeToTrigger
            ? new $exceptionTypeToTrigger(self::$exceptionMessage)
            : null;
        $inspector = new Inspector($catchType, $fallbackCatchType);

        // callback - the actual callable picked compared below
        $expectedCallback = $expectedUseCallback
            ? [$exceptionCallback]
            : ($expectedUseFallbackCallback
                ? [$fallbackExceptionCallback]
                : []
            );

        // channels - pick the config's values when not specified
        $channels = Framework::config()->getChannelsWhenKnown();
        if ((!$expectedGetChannels) && ($expectedGetKnown) && (count($channels))) {
            $expectedGetChannels = $channels;
        }
        $channels = Framework::config()->getChannelsWhenNotKnown();
        if ((!$expectedGetChannels) && (count($channels))) {
            $expectedGetChannels = $channels;
        }
        if (!$expectedGetChannels) {
            $expectedGetChannels = [];
        }

        // level - pick the config's values when not specified
        if ($expectedGetKnown) {
            $expectedGetLevel = $expectedGetLevel
                ?? Framework::config()->getLevelWhenKnown()
                ?? Framework::config()->getLevelWhenNotKnown();
        } else {
            $expectedGetLevel = $expectedGetLevel
                ?? Framework::config()->getLevelWhenNotKnown();
        }

        // report
        $expectedShouldReport ??= Framework::config()->getReport();

        $matched = $exception
            ? $inspector->checkForMatch($exception)
            : null;
        self::assertSame($expectedCheckForMatch, $matched);
        self::assertSame($expectedGetExceptionClasses, $inspector->getExceptionClasses());
        self::assertSame($expectedCallback, $inspector->resolveCallbacks());
        self::assertSame($expectedGetKnown, $inspector->resolveKnown());
        self::assertSame(count($expectedGetKnown) > 0, $inspector->hasKnown());
        self::assertSame($expectedGetChannels, $inspector->resolveChannels());
        self::assertSame($expectedGetLevel, $inspector->resolveLevel());
        self::assertSame($expectedShouldReport, $inspector->shouldReport());
        self::assertSame($expectedRethrow, $inspector->pickRethrow());
        self::assertSame($expectedDefault, $inspector->resolveDefault());
    }

    /**
     * Build a CatchType from InitMethodCalls.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @param callable    $callback        The exception callback to use.
     * @return CatchType
     * @throws Exception When a method doesn't exist when instantiating the Catch Type class.
     */
    private static function buildCatchType(MethodCalls $initMethodCalls, callable $callback): CatchType
    {
        $catchTypeObject = null;
        foreach ($initMethodCalls->getCalls() as $methodCall) {

            $method = $methodCall->getMethod();
            $args = $methodCall->getArgs();

            // place the exception callback into the args for calls to callback()
            if (($method == 'callback') && ($args[0] ?? null)) {
                $args[0] = $callback;
            }

            $toCall = [$catchTypeObject ?? CatchType::class, $method];
            if (is_callable($toCall)) {
                $catchTypeObject = call_user_func_array($toCall, $args);
            } else {
                throw new Exception("Can't call method $method on class CatchType");
            }
        }
        /** @var CatchType|null $catchTypeObject */
        return $catchTypeObject ?? new CatchType();
    }



    /**
     * DataProvider for test_that_inspector_operates_properly().
     *
     * Provide the different combinations of how the CatchType object can be set up and called.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function inspectorDataProvider(): array
    {
        $typeCombinations = [
            null, // don't call
            [Throwable::class],
            [InvalidArgumentException::class],
            [DivisionByZeroError::class],
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
            ['ABC-123'],
//            [['ABC-123', 'DEF-456']],
        ];

        $channelCombinations = [
            null, // don't call
            ['stack'],
            ['stack', 'slack'],
        ];

        $levelCombinations = [
            null, // don't call
            [Settings::REPORTING_LEVEL_INFO],
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

        $triggerExceptionTypes = [
            null, // don't throw an exception
            Exception::class,
            InvalidArgumentException::class,
        ];

        $defaultCombinations = [
            null, // don't call
            [true],
            ['something'],
        ];



        $return = [];

        foreach ($triggerExceptionTypes as $exceptionTypeToTrigger) {

            $allInitMethodCallGroups = [];
            $allInitMethodCallGroup = [];
            foreach ($typeCombinations as $type) {

                foreach ($matchCombinations as $match) {
                    $allInitMethodCallGroup[] = MethodCalls::new()
                        ->add('catch', $type)
                        ->add('match', $match);
                }

                foreach ($matchRegexCombinations as $regex) {
                    $allInitMethodCallGroup[] = MethodCalls::new()
                        ->add('catch', $type)
                        ->add('matchRegex', $regex);
                }

                $allInitMethodCallGroups[] = $allInitMethodCallGroup;
            }

            $allInitMethodCallGroup = [];
            foreach ($typeCombinations as $type) {

                foreach ($callbackCombinations as $callback) {
                    $allInitMethodCallGroup[] = MethodCalls::new()
                        ->add('catch', $type)
                        ->add('callback', $callback);
                }

                $allInitMethodCallGroups[] = $allInitMethodCallGroup;
            }

            $allInitMethodCallGroup = [];
            foreach ($typeCombinations as $type) {

                foreach ($knownCombinations as $known) {
                    foreach ($channelCombinations as $channels) {
                        $allInitMethodCallGroup[] = MethodCalls::new()
                            ->add('catch', $type)
                            ->add('known', $known)
                            ->add('channels', $channels);
                    }
                }

                $allInitMethodCallGroups[] = $allInitMethodCallGroup;
            }

            $allInitMethodCallGroup = [];
            foreach ($typeCombinations as $type) {

                foreach ($knownCombinations as $known) {
                    foreach ($levelCombinations as $level) {
                        $allInitMethodCallGroup[] = MethodCalls::new()
                            ->add('catch', $type)
                            ->add('known', $known)
                            ->add('level', $level);
                    }
                }

                $allInitMethodCallGroups[] = $allInitMethodCallGroup;
            }

            $allInitMethodCallGroup = [];
            foreach ($typeCombinations as $type) {

                foreach ($reportCombinations as $report) {
                    $allInitMethodCallGroup[] = MethodCalls::new()
                        ->add('catch', $type)
                        ->add('report', $report);
                }

                foreach ($rethrowCombinations as $rethrow) {
                    $allInitMethodCallGroup[] = MethodCalls::new()
                        ->add('catch', $type)
                        ->add('rethrow', $rethrow);
                }

                $allInitMethodCallGroups[] = $allInitMethodCallGroup;
            }


            // create the combinations of these calls
            foreach ($allInitMethodCallGroups as $allInitMethodCallGroup) {
                foreach ($allInitMethodCallGroup as $initMethodCalls1) {
                    foreach ($allInitMethodCallGroup as $initMethodCalls2) {

                        if (!$initMethodCalls1->hasCalls()) {
                            continue;
                        }
                        if (!$initMethodCalls2->hasCalls()) {
                            continue;
                        }

                        $return[] = self::buildParams(
                            $initMethodCalls2,
                            $initMethodCalls1,
                            $exceptionTypeToTrigger
                        );
                    }
                }
            }
        }





        // different rethrow values
        $rethrowCombinations = [
            null, // don't call
            [], // called with no arguments
            [true],
            [false],
            [fn() => true],
        ];

        $allInitMethodCallGroups = [];
        foreach ($rethrowCombinations as $rethrow) {
            $allInitMethodCallGroups[] = MethodCalls::new()->add('rethrow', $rethrow);
        }

        foreach ($allInitMethodCallGroups as $initMethodCalls1) {
            foreach ($allInitMethodCallGroups as $initMethodCalls2) {

                if (!$initMethodCalls1->hasCalls()) {
                    continue;
                }
                if (!$initMethodCalls2->hasCalls()) {
                    continue;
                }

                $return[] = self::buildParams(
                    $initMethodCalls2,
                    $initMethodCalls1,
                    $exceptionTypeToTrigger
                );
            }
        }





        // method calls that aren't multiplied out by the exception types and catch combinations
        $exceptionTypeToTrigger = null;

        $allInitMethodCallGroups = [];
        foreach ($defaultCombinations as $default) {
            $allInitMethodCallGroups[] = MethodCalls::new()->add('default', $default);
        }

        foreach ($allInitMethodCallGroups as $initMethodCalls1) {
            foreach ($allInitMethodCallGroups as $initMethodCalls2) {

                if (!$initMethodCalls1->hasCalls()) {
                    continue;
                }
                if (!$initMethodCalls2->hasCalls()) {
                    continue;
                }

                $return[] = self::buildParams(
                    $initMethodCalls2,
                    $initMethodCalls1,
                    $exceptionTypeToTrigger
                );
            }
        }

        return $return;
    }



    /**
     * Determine the parameters to pass to the test_that_inspector_operates_properly test.
     *
     * @param MethodCalls $initMethodCalls    Methods to call when initialising the CatchType object.
     * @param MethodCalls $fallbackCalls      Methods to call when initialising the fallback CatchType object.
     * @param string|null $exceptionToTrigger The exception type to trigger (if any).
     * @return array<string, mixed>
     */
    private static function buildParams(
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
        ?string $exceptionToTrigger = null
    ): array {

        $willBeCaughtBy = self::determineWhatWillCatchTheException(
            $exceptionToTrigger,
            $fallbackCalls,
            $initMethodCalls
        );

        $catchTypeHasCallback = count($initMethodCalls->getAllCallArgsFlat('callback')) > 0;
        $fallbackHasCallback = count($fallbackCalls->getAllCallArgsFlat('callback')) > 0;

        $catchTypeKnown = $initMethodCalls->getAllCallArgsFlat('known');
        $fallbackKnown = $fallbackCalls->getAllCallArgsFlat('known');

        $catchTypeChannels = $initMethodCalls->getAllCallArgsFlat('channels');
        $catchTypeChannels = $catchTypeChannels ?: $initMethodCalls->getAllCallArgsFlat('channel');
        $fallbackChannels = $fallbackCalls->getAllCallArgsFlat('channels');
        $fallbackChannels = $fallbackChannels ?: $fallbackCalls->getAllCallArgsFlat('channel');

        $catchTypeLevel = last($initMethodCalls->getAllCallArgsFlat('level'));
        $catchTypeLevel = ($catchTypeLevel !== false)
            ? $catchTypeLevel
            :  null;
        $fallbackLevel = last($fallbackCalls->getAllCallArgsFlat('level'));
        $fallbackLevel = ($fallbackLevel !== false)
            ? $fallbackLevel
            : null;

        $catchTypeReport = null;
        foreach ($initMethodCalls->getCalls(['report', 'dontReport']) as $methodCall) {
            /** @var 'report'|'dontReport' $method */
            $method = $methodCall->getMethod();
            $catchTypeReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => false,
            };
        }

        $fallbackReport = null;
        foreach ($fallbackCalls->getCalls(['report', 'dontReport']) as $methodCall) {
            /** @var 'report'|'dontReport' $method */
            $method = $methodCall->getMethod();
            $fallbackReport = match ($method) {
                'report' => (bool) ($methodCall->getArgs()[0] ?? true),
                'dontReport' => false,
            };
        }

        $catchTypeRethrow = null;
        foreach ($initMethodCalls->getCalls(['rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            $catchTypeRethrow = match ($method) {
                'rethrow' => $methodCall->getArgs()[0] ?? true,
                'dontRethrow' => false,
            };
        }

        $fallbackRethrow = null;
        foreach ($fallbackCalls->getCalls(['rethrow', 'dontRethrow']) as $methodCall) {
            /** @var 'rethrow'|'dontRethrow' $method */
            $method = $methodCall->getMethod();
            $fallbackRethrow = match ($method) {
                'rethrow' => $methodCall->getArgs()[0] ?? true,
                'dontRethrow' => false,
            };
        }

        $catchTypeDefault = null;
        foreach ($initMethodCalls->getCalls(['default']) as $methodCall) {
            $catchTypeDefault = $methodCall->getArgs()[0] ?? null;
        }

        $fallbackDefault = null;
        foreach ($fallbackCalls->getCalls(['default']) as $methodCall) {
            $fallbackDefault = $methodCall->getArgs()[0] ?? null;
        }

        return [
            'initMethodCalls' => $initMethodCalls,
            'fallbackInitMethodCalls' => $fallbackCalls,
            'exceptionToTrigger' => $exceptionToTrigger,
            'expectedCheckForMatch' => $exceptionToTrigger
                ? !is_null($willBeCaughtBy)
                : null,
            'expectedGetExceptionClasses' => $initMethodCalls->getAllCallArgsFlat('catch'),
            'expectedUseCallback' => $catchTypeHasCallback,
            'expectedUseFallbackCallback' => !$catchTypeHasCallback && $fallbackHasCallback,
            'expectedGetKnown' => $catchTypeKnown ?: $fallbackKnown,
            'expectedGetChannels' => $catchTypeChannels ?: $fallbackChannels ?: ['stack'],
            'expectedGetLevel' => $catchTypeLevel ?: $fallbackLevel ?: Settings::REPORTING_LEVEL_ERROR,
            'expectedShouldReport' => $catchTypeReport ?? $fallbackReport ?? true, // default true
            'expectedShouldRethrow' => $catchTypeRethrow ?? $fallbackRethrow ?? false, // default false
            'expectedDefault' => $catchTypeDefault ?? $fallbackDefault ?? null, // default null
        ];
    }



    /**
     * Determine if a thrown exception will be caught.
     *
     * @param string|null      $exceptionToTrigger The exception type to trigger (if any).
     * @param MethodCalls      $fallbackCalls      Methods to call when initialising the fallback CatchType object.
     * @param MethodCalls|null $initMethodCalls    Methods to call when initialising the CatchType object.
     * @return MethodCalls|null
     */
    private static function determineWhatWillCatchTheException(
        ?string $exceptionToTrigger,
        MethodCalls $fallbackCalls,
        ?MethodCalls $initMethodCalls,
    ): ?MethodCalls {

        if (is_null($exceptionToTrigger)) {
            return null;
        }


        /** @var string[] $fallbackMatchStrings */
        $fallbackMatchStrings = $fallbackCalls->getAllCallArgsFlat('match');

        /** @var string[] $fallbackMatchRegexes */
        $fallbackMatchRegexes = $fallbackCalls->getAllCallArgsFlat('matchRegex');

        if ($initMethodCalls) {

            // check the main CatchType settings first
            /** @var string[] $catchClasses */
            $catchClasses = $initMethodCalls->getAllCallArgsFlat('catch');
            /** @var string[] $matchStrings */
            $matchStrings = $initMethodCalls->getAllCallArgsFlat('match') ?: $fallbackMatchStrings;
            /** @var string[] $matchRegex */
            $matchRegex = $initMethodCalls->getAllCallArgsFlat('matchRegex') ?: $fallbackMatchRegexes;

            $a = self::checkIfMatchesMatch($matchStrings);
            $b = self::checkIfMatchRegexesMatch($matchRegex);

            if (
                (self::checkIfExceptionClassesMatch($exceptionToTrigger, $catchClasses))
                && ($a || $b || (is_null($a) && is_null($b)))
            ) {
                return $initMethodCalls;
            }
        }



//        // if there are CatchTypes, and the fall-back doesn't define class/es to catch, then stop
//        if (($initMethodCalls) && (!$fallbackCalls->hasCall('catch'))) {
//            return null;
//        }
//
//        // check the fallback settings second
//        if (
//            (self::checkIfExceptionClassesMatch($exceptionToTrigger, $fallbackCatchClasses))
//            && (self::checkIfMatchesMatch($fallbackMatchStrings))
//        ) {
//            return $fallbackCalls;
//        }

        return null;
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
     * Check if the regex strings match.
     *
     * @param string[] $regexes The regular expressions to try.
     * @return boolean|null
     */
    private static function checkIfMatchRegexesMatch(array $regexes): ?bool
    {
        if (!count($regexes)) {
            return null;
        }

        foreach ($regexes as $regex) {
            if (preg_match($regex, self::$exceptionMessage)) {
                return true;
            }
        }
        return false;
    }
}
