<?php

namespace CodeDistortion\ClarityControl\Tests\Unit\Support;

use CodeDistortion\ClarityControl\Support\Support;
use CodeDistortion\ClarityControl\Tests\PHPUnitTestCase;

/**
 * Test the Support class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class SupportUnitTest extends PHPUnitTestCase
{
    /**
     * Test that arguments get normalised properly.
     *
     * @test
     * @dataProvider argumentDataProvider
     *
     * @param mixed[]        $previous The "previous" arguments.
     * @param array<mixed[]> $args     The "new" arguments to add.
     * @param mixed[]        $expected The expected output.
     * @return void
     */
    public static function test_normalise_args_method(array $previous, array $args, array $expected): void
    {
        $normalised = Support::normaliseArgs($previous, $args);
        self::assertSame($expected, $normalised);
    }

    /**
     * DataProvider for test_that_arguments_are_normalised().
     *
     * @return array<array<string, mixed>>
     */
    public static function argumentDataProvider(): array
    {
        $value1 = 'a';
        $value2 = 'b';
        $value3 = 'c';
        $value4 = 'd';

        $array1 = [$value1];
        $array2 = [$value2];
        $array3 = [$value3];
        $array4 = [$value4];

        $object1 = (object) [$value1 => $value1];
        $object2 = (object) [$value2 => $value2];
        $object3 = (object) [$value3 => $value3];
        $object4 = (object) [$value4 => $value4];

        return [
            ...self::buildSetOfArgs($value1, $value2, $value3, $value4),
            ...self::buildSetOfArgs($array1, $array2, $array3, $array4),
            ...self::buildSetOfArgs($object1, $object2, $object3, $object4),
        ];
    }

    /**
     * Build combinations of inputs to test.
     *
     * @param mixed $one   Value 1.
     * @param mixed $two   Value 2.
     * @param mixed $three Value 3.
     * @param mixed $four  Value 4.
     * @return array<array<string, mixed>>
     */
    private static function buildSetOfArgs(mixed $one, mixed $two, mixed $three, mixed $four): array
    {
        return [
            self::buildArgs([], []),
            self::buildArgs([$one, $two], []),
            self::buildArgs([], [$one, $two]),
            self::buildArgs([$one, $two], [$three, $four]),
            self::buildArgs([$one, $two], [$two, $three]),
            self::buildArgs([$one, $one], []),
            self::buildArgs([], [$one, $one]),
            self::buildArgs([null], [$one, $two]),
            self::buildArgs([$one, $two], [null]),
        ];
    }

    /**
     * @param mixed[]               $previous The "previous" arguments.
     * @param array<integer, mixed> $args     The "new" arguments to add.
     * @return array<string, mixed>
     */
    private static function buildArgs(array $previous, array $args): array
    {
        foreach ($args as $arg) {
            $arg = is_array($arg)
                ? $arg
                : [$arg];
            $previous = array_merge($previous, $arg);
        }

        $expected = array_values(
            array_unique(
                array_filter($previous),
                SORT_REGULAR
            )
        );

        return [
            'previous' => $previous,
            'args' => $args,
            'expected' => $expected,
        ];
    }
}
