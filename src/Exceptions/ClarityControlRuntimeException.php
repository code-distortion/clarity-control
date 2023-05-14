<?php

namespace CodeDistortion\ClarityControl\Exceptions;

/**
 * Exception generated when executing Clarity Control.
 */
class ClarityControlRuntimeException extends ClarityControlException
{
    /**
     * An invalid rethrow value was returned by a callback.
     *
     * @return self
     */
    public static function invalidRethrowValue(): self
    {
        return new self('Invalid rethrow value given. It must be a boolean, null, or a Throwable');
    }
}
