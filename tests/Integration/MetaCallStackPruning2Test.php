<?php

namespace CodeDistortion\ClarityControl\Tests\Integration;

use CodeDistortion\ClarityContext\Clarity;
use CodeDistortion\ClarityContext\Context;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\CallMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ContextMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\LastApplicationFrameMeta;
use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use Exception;

/**
 * Test the MetaCallStack class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class MetaCallStackPruning2Test extends LaravelTestCase
{
    /**
     * Test the purging of meta-data after a fork in the execution tree.
     *
     * The CallMeta from $closure2 should be purged.
     *
     * ----- closure2 (Clarity context added, exception thrown or caught)
     *   \-- closure3 (Clarity context added, and throws exception)
     *
     * @test
     * @dataProvider purgeMetaDataDataProvider
     *
     * @param boolean $closure2AddContext           Should $closure2 add context?.
     * @param boolean $closure2ClarityRunAndCatch   Should $closure2 run and catch an exception?.
     * @param boolean $closure3AddContext           Should $closure3 add context?.
     * @param boolean $closure3ClarityRunAndRethrow Should $closure3 run and rethrow an exception?.
     * @param integer $expectedCallMetaCount        The Expected CallMeta count in the Context object.
     * @param integer $expectedContextMetaCount     The Expected ContextMeta count in the Context object.
     * @return void
     */
    public static function test_purge_of_meta_data_after_fork_in_execution_tree(
        bool $closure2AddContext,
        bool $closure2ClarityRunAndCatch,
        bool $closure3AddContext,
        bool $closure3ClarityRunAndRethrow,
        int $expectedCallMetaCount,
        int $expectedContextMetaCount,
    ): void {

        $callback = function (Context $context) use (
            $expectedCallMetaCount,
            $expectedContextMetaCount,
        ) {

            $callStack = $context->getCallStack();

            self::assertCount(3 + $expectedCallMetaCount + $expectedContextMetaCount, $callStack->getMeta());
            self::assertCount($expectedCallMetaCount, $callStack->getMeta(CallMeta::class));
            self::assertCount($expectedContextMetaCount, $callStack->getMeta(ContextMeta::class));
            self::assertCount(1, $callStack->getMeta(ExceptionThrownMeta::class));
            self::assertCount(1, $callStack->getMeta(ExceptionCaughtMeta::class));
            self::assertCount(1, $callStack->getMeta(LastApplicationFrameMeta::class));

            if ($expectedContextMetaCount) {
                /** @var ContextMeta[] $contextMeta */
                $contextMeta = $callStack->getMeta(ContextMeta::class);
                self::assertSame('context3', $contextMeta[0]->getContext());
            }
        };

        $closure3 = function () use ($closure3AddContext, $closure3ClarityRunAndRethrow) {
            if ($closure3AddContext) {
                Clarity::context('context3');
            }

            $closure3ClarityRunAndRethrow
                ? Control::prepare(fn() => throw new Exception())->rethrow()->execute()
                : throw new Exception();
        };

        $closure2 = function () use ($closure2AddContext, $closure2ClarityRunAndCatch) {
            if ($closure2AddContext) {
                Clarity::context('context2');
            }

            if ($closure2ClarityRunAndCatch) {
                Control::run(fn() => throw new Exception());
            }
        };

        $closure1 = function () use ($closure2, $closure3) {
            $closure2();
            $closure3();
        };

        Control::prepare($closure1)
            ->callback($callback)
            ->execute();
    }

    /**
     * DataProvider for test_purge_of_meta_data_after_fork_in_execution_tree().
     *
     * @return array<array<string, boolean|integer>>
     */
    public static function purgeMetaDataDataProvider(): array
    {
        $return = [];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => true,
            'closure3AddContext' => false,
            'closure3ClarityRunAndRethrow' => false,
            'expectedCallMetaCount' => 1,
            'expectedContextMetaCount' => 0,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => false,
            'closure3AddContext' => false,
            'closure3ClarityRunAndRethrow' => false,
            'expectedCallMetaCount' => 1,
            'expectedContextMetaCount' => 0,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => true,
            'closure3AddContext' => true,
            'closure3ClarityRunAndRethrow' => false,
            'expectedCallMetaCount' => 1,
            'expectedContextMetaCount' => 1,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => false,
            'closure3AddContext' => true,
            'closure3ClarityRunAndRethrow' => false,
            'expectedCallMetaCount' => 1,
            'expectedContextMetaCount' => 1,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => true,
            'closure3AddContext' => false,
            'closure3ClarityRunAndRethrow' => true,
            'expectedCallMetaCount' => 2,
            'expectedContextMetaCount' => 0,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => false,
            'closure3AddContext' => false,
            'closure3ClarityRunAndRethrow' => true,
            'expectedCallMetaCount' => 2,
            'expectedContextMetaCount' => 0,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => true,
            'closure3AddContext' => true,
            'closure3ClarityRunAndRethrow' => true,
            'expectedCallMetaCount' => 2,
            'expectedContextMetaCount' => 1,
        ];

        $return[] = [
            'closure2AddContext' => true,
            'closure2ClarityRunAndCatch' => false,
            'closure3AddContext' => true,
            'closure3ClarityRunAndRethrow' => true,
            'expectedCallMetaCount' => 2,
            'expectedContextMetaCount' => 1,
        ];

        return $return;
    }
}
