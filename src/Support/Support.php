<?php

namespace CodeDistortion\ClarityControl\Support;

/**
 * Common methods, shared throughout Clarity Control.
 */
class Support
{
    /**
     * Loop through the arguments, and normalise them into a single array, merged with previously existing values.
     *
     * @internal
     *
     * @param mixed[] $previous The values that were set previously, to be merged into.
     * @param mixed[] $args     The arguments that were passed to the method that called this one.
     * @return mixed[]
     */
    public static function normaliseArgs(array $previous, array $args): array
    {
        foreach ($args as $arg) {
            $arg = is_array($arg)
                ? $arg
                : [$arg];
            $previous = array_merge($previous, $arg);
        }
        return array_values(
            array_unique(
                array_filter($previous),
                SORT_REGULAR
            )
        );
    }
}
