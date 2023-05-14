<?php

namespace CodeDistortion\ClarityControl\Tests\Unit\Traits;

use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Exceptions\ClarityControlInitialisationException;
use CodeDistortion\ClarityControl\Settings;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use Exception;
use ReflectionClass;

/**
 * Test the HasCatchTypes trait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class HasCatchTypesUnitTest extends LaravelTestCase
{
    /**
     * Run a static Control methods before initialisation (i.e. methods that aren't prepare() or run()) and test that an
     * exception is thrown.
     *
     * @test
     * @dataProvider initialisationMethodsDataProvider
     *
     * @param string  $method The initialisation method to call.
     * @param mixed[] $args   The arguments to pass.
     * @return void
     * @throws Exception When the Control method cannot be called.
     */
    public static function test_calling_context_before_other_initialisation_methods(string $method, array $args): void
    {
        $exceptionWasThrown = false;
        try {

            $r = new ReflectionClass(Control::class);
            $control = $r->newInstanceWithoutConstructor();

            is_callable($toCall = [$control, $method])
                ? call_user_func_array($toCall, $args)
                : throw new Exception("Can't call method $method on class Control");

        } catch (ClarityControlInitialisationException) {
            $exceptionWasThrown = true;
        }
        self::assertTrue($exceptionWasThrown);
    }

    /**
     * DataProvider for test_calling_context_before_other_initialisation_methods().
     *
     * @return array<int, array<int|string, array<int, callable|string>|string>>
     */
    public static function initialisationMethodsDataProvider(): array
    {
        return [
            ['method' => 'catch', [Exception::class]],
            ['method' => 'match', ['abc']],
            ['method' => 'matchRegex', ['/^abc/']],
            ['method' => 'callback', [fn() => 'a']],
            ['method' => 'callbacks', [fn() => 'a']],
            ['method' => 'known', ['abc']],
            ['method' => 'channel', ['abc']],
            ['method' => 'channels', ['abc']],
            ['method' => 'level', [Settings::REPORTING_LEVEL_WARNING]],
            ['method' => 'debug', []],
            ['method' => 'info', []],
            ['method' => 'notice', []],
            ['method' => 'warning', []],
            ['method' => 'error', []],
            ['method' => 'critical', []],
            ['method' => 'alert', []],
            ['method' => 'emergency', []],
            ['method' => 'report', []],
            ['method' => 'dontReport', []],
            ['method' => 'rethrow', []],
            ['method' => 'rethrow', [fn() => true]],
            ['method' => 'dontRethrow', []],
            ['method' => 'suppress', []],
            ['method' => 'default', ['abc']],
        ];
    }
}
