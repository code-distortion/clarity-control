<?php

namespace CodeDistortion\ClarityControl\Tests\Unit\Support;

use CodeDistortion\ClarityContext\Exceptions\ClarityContextInitialisationException;
use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Support\Inspector;
use CodeDistortion\ClarityControl\Support\InternalSettings;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use CodeDistortion\ClarityControl\Tests\Support\MethodCalls;
use Exception;

/**
 * Test configuration related interactions.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class InspectorConfigUnitTest extends LaravelTestCase
{
    /**
     * Test that the config values are picked up properly by Inspector.
     *
     * @test
     * @dataProvider configDataProvider
     *
     * @param array<string, mixed> $config               Config values to set.
     * @param MethodCalls          $initMethodCalls      Methods to call when initialising the CatchType object.
     * @param MethodCalls          $fallbackCalls        Methods to call when initialising the fallback CatchType
     *                                                   object.
     * @param string[]             $expectedGetChannels  The expected channels.
     * @param string|null          $expectedGetLevel     The expected level.
     * @param boolean|null         $expectedShouldReport The expected should-report.
     * @return void
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    public static function test_that_config_values_are_used_by_inspector(
        array $config,
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
        array $expectedGetChannels,
        ?string $expectedGetLevel,
        ?bool $expectedShouldReport,
    ): void {

        Framework::config()->updateConfig($config);

        $fallbackCallback = fn() => 'hello';
        $callback = fn() => 'hello';

        $fallbackCatchType = self::buildCatchType($fallbackCalls, $fallbackCallback);
        $catchType = self::buildCatchType($initMethodCalls, $callback);

        $inspector = new Inspector($catchType, $fallbackCatchType);

        self::assertSame($expectedGetChannels, $inspector->resolveChannels());
        self::assertSame($expectedGetLevel, $inspector->resolveLevel());
        self::assertSame($expectedShouldReport, $inspector->shouldReport());
    }

    /**
     * Build a CatchType from InitMethodCalls.
     *
     * @param MethodCalls $initMethodCalls Methods to call when initialising the CatchType object.
     * @param callable    $callback        The exception callback to use.
     * @return CatchType
     * @throws Exception When a method doesn't exist when instantiating the CatchType class.
     */
    private static function buildCatchType(MethodCalls $initMethodCalls, callable $callback): CatchType
    {
        $catchTypeObject = new CatchType();
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
        /** @var CatchType $catchTypeObject */
        return $catchTypeObject;
    }



    /**
     * DataProvider for test_that_config_values_are_used().
     *
     * Provide the different combinations of config values and CatchTypes.
     *
     * @return array<integer, array<string, mixed>>
     */
    public static function configDataProvider(): array
    {
        $return = [];



        $channelsWhenKnownCombinations = [
            'known-channel',
            ['known-channel1', 'known-channel2'],
            null,
        ];

        $channelsWhenNotKnownCombinations = [
            'default-channel',
            ['default-channel1', 'default-channel2'],
            null,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('channel', ['catch-type-channel']),
            MethodCalls::add('channel', ['catch-type-channel'])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('channel', ['fallback-catch-type-channel']),
            MethodCalls::add('channel', ['fallback-catch-type-channel'])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        foreach ($channelsWhenKnownCombinations as $whenKnown) {
            foreach ($channelsWhenNotKnownCombinations as $whenNotKnown) {
                foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                    foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                        $config = [
                            'logging.default' => 'default-channel',
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_known' => $whenKnown,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_not_known'
                                => $whenNotKnown,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_known' => null,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_not_known' => null,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.report' => true,
                        ];

                        $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                    }
                }
            }
        }



        $levelWhenKnownCombinations = [
            Settings::REPORTING_LEVEL_DEBUG,
            null,
        ];

        $levelWhenNotKnownCombinations = [
            Settings::REPORTING_LEVEL_EMERGENCY,
            null,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('level', [Settings::REPORTING_LEVEL_DEBUG]),
            MethodCalls::add('level', [Settings::REPORTING_LEVEL_DEBUG])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('level', [Settings::REPORTING_LEVEL_EMERGENCY]),
            MethodCalls::add('level', [Settings::REPORTING_LEVEL_EMERGENCY])->add('known', ['a']),
            MethodCalls::new(),
            MethodCalls::new()->add('known', ['a']),
        ];

        foreach ($levelWhenKnownCombinations as $whenKnown) {
            foreach ($levelWhenNotKnownCombinations as $whenNotKnown) {
                foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                    foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                        $config = [
                            'logging.default' => 'default-channel',
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_known' => null,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_not_known' => null,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_known' => $whenKnown,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_not_known' => $whenNotKnown,
                            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.report' => true,
                        ];

                        $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                    }
                }
            }
        }



        $reportCombinations = [
            true,
            false,
            null,
        ];

        $catchTypeMethodCombinations = [
            MethodCalls::add('report', [true]),
            MethodCalls::add('report', [false]),
            MethodCalls::new(),
        ];

        $fallbackCatchTypeCombinations = [
            MethodCalls::add('report', [true]),
            MethodCalls::add('report', [false]),
            MethodCalls::new(),
        ];

        foreach ($reportCombinations as $report) {
            foreach ($catchTypeMethodCombinations as $initMethodCalls) {
                foreach ($fallbackCatchTypeCombinations as $fallbackCalls) {

                    $config = [
                        'logging.default' => 'default-channel',
                        InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_known' => null,
                        InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.when_not_known' => null,
                        InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_known' => null,
                        InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_not_known' => null,
                        InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.report' => $report,
                    ];

                    $return[] = self::buildParams($config, $initMethodCalls, $fallbackCalls);
                }
            }
        }

        return $return;
    }



    /**
     * Determine the parameters to pass to the test_that_config_values_are_used test.
     *
     * @param array<string, mixed> $config          Config values to set.
     * @param MethodCalls          $initMethodCalls Methods to call when initialising the CatchType object.
     * @param MethodCalls          $fallbackCalls   Methods to call when initialising the fallback CatchType object.
     * @return array<string, mixed>
     */
    private static function buildParams(
        array $config,
        MethodCalls $initMethodCalls,
        MethodCalls $fallbackCalls,
    ): array {

        $catchTypeKnown = $initMethodCalls->getAllCallArgsFlat('known');
        $fallbackKnown = $fallbackCalls->getAllCallArgsFlat('known');
        $isKnown = ((count($catchTypeKnown)) || (count($fallbackKnown)));

        $catchTypeChannels = $initMethodCalls->getAllCallArgsFlat('channel');
        $fallbackChannels = $fallbackCalls->getAllCallArgsFlat('channel');

        $catchTypeLevel = last($initMethodCalls->getAllCallArgsFlat('level'));
        $catchTypeLevel = ($catchTypeLevel !== false)
            ? $catchTypeLevel
            : null;
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


        $knownKey = $isKnown ? 'when_known' : 'when_not_known';
        $expectedGetChannels = $catchTypeChannels
            ?: $fallbackChannels
                ?: $config[InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.channels.' . $knownKey]
                ?? [$config['logging.default']];
        $expectedGetChannels = is_array($expectedGetChannels)
            ? $expectedGetChannels
            : [$expectedGetChannels];



        $expectedGetLevel = $catchTypeLevel
            ?? $fallbackLevel
            ?? $config[InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.' . $knownKey]
            ?? Settings::REPORTING_LEVEL_ERROR;  // default



        $expectedShouldReport = $catchTypeReport
            ?? $fallbackReport
            ?? $config[InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.report']
            ?? true; // default true



        return [
            'config' => $config,
            'initMethodCalls' => $initMethodCalls,
            'fallbackInitMethodCalls' => $fallbackCalls,
            'expectedGetChannels' => $expectedGetChannels,
            'expectedGetLevel' => $expectedGetLevel,
            'expectedShouldReport' => $expectedShouldReport,
        ];
    }





    /**
     * Test that an invalid "level" value from the config, will trigger an exception when accessed by Inspector.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_invalid_config_level_triggers_an_exception_within_inspector(): void
    {
        Framework::config()->updateConfig(
            [InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.level.when_not_known' => 'INVALID']
        );

        $inspector = new Inspector(new CatchType());

        $exceptionWasThrown = false;
        try {
            $inspector->resolveLevel();
        } catch (ClarityContextInitialisationException) {
            $exceptionWasThrown = true;
        }

        self::assertTrue($exceptionWasThrown);
    }
}
