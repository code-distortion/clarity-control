<?php

namespace CodeDistortion\ClarityControl\Exceptions;

use CodeDistortion\ClarityControl\Settings;

/**
 * Exception generated when initialising Clarity Control.
 */
class ClarityControlInitialisationException extends ClarityControlException
{
    /**
     * An invalid level was specified.
     *
     * @param string|null $level The invalid level.
     * @return self
     */
    public static function levelNotAllowed(?string $level): self
    {
        return new self("Level \"$level\" is not allowed. Please choose from: " . implode(', ', Settings::LOG_LEVELS));
    }

    /**
     * The caller must call prepare() before calling the other methods like known() and catch().
     *
     * @param string $method The method that was called.
     * @return self
     */
    public static function runPrepareFirst(string $method): self
    {
        return new self("Please call Control::prepare(…) first before calling $method()");
    }
}
