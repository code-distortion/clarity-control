<?php

namespace CodeDistortion\ClarityControl\Tests\Unit\Exceptions;

use CodeDistortion\ClarityControl\Exceptions\ClarityControlInitialisationException;
use CodeDistortion\ClarityControl\Exceptions\ClarityControlRuntimeException;
use CodeDistortion\ClarityControl\Tests\PHPUnitTestCase;

/**
 * Test the Exception classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ExceptionUnitTest extends PHPUnitTestCase
{
    /**
     * Test the messages that exceptions generate.
     *
     * @test
     *
     * @return void
     */
    public static function test_exception_messages(): void
    {
        // ClarityControlInitialisationException

        self::assertSame(
            'Level "blah" is not allowed. '
            . 'Please choose from: debug, info, notice, warning, error, critical, alert, emergency',
            ClarityControlInitialisationException::levelNotAllowed('blah')->getMessage()
        );

        self::assertSame(
            'Please call Control::prepare(…) first before calling someMethod()',
            ClarityControlInitialisationException::runPrepareFirst('someMethod')->getMessage()
        );



        // ClarityControlRuntimeException

        self::assertSame(
            'Invalid rethrow value given. It must be a boolean, null, or a Throwable',
            ClarityControlRuntimeException::invalidRethrowValue()->getMessage()
        );
    }
}
