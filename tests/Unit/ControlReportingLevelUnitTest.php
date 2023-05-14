<?php

namespace CodeDistortion\ClarityControl\Tests\Unit;

use CodeDistortion\ClarityContext\Context;
use CodeDistortion\ClarityContext\Support\Environment;
use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use CodeDistortion\ClarityControl\Tests\Support\MethodCalls;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Test the Control class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ControlReportingLevelUnitTest extends LaravelTestCase
{
    /**
     * Test that the Control class' methods set the log-levels properly when reporting an exception.
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
        $control = Control::prepare(fn() => throw new Exception())
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

//    /**
//     * Assert that the logger should not be called at all.
//     *
//     * @param string $level The log reporting level to check.
//     * @return void
//     * @throws Exception When the framework isn't recognised.
//     */
//    private static function logShouldNotReceive(string $level): void
//    {
//        if (!Environment::isLaravel()) {
//            throw new Exception('Log checking needs to be updated for the current framework');
//        }
//
//        Log::shouldReceive($level)->atMost()->times(0);
//    }
}
