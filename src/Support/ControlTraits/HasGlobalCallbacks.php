<?php

namespace CodeDistortion\ClarityControl\Support\ControlTraits;

use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityControl\Support\InternalSettings;
use CodeDistortion\ClarityControl\Support\Support;

/**
 * Methods to store and retrieve "global" callbacks (that always run when an exception occurs).
 */
trait HasGlobalCallbacks
{
    /**
     * Add a "global" callback, to always run when an exception occurs.
     *
     * @param callable $callback The callback to run.
     * @return void
     */
    public static function globalCallback(callable $callback): void
    {
        self::globalCallbacks($callback);
    }

    /**
     * Add "global" callbacks, to always run when an exception occurs.
     *
     * @param callable|callable[] $callbacks     The callback/s to run.
     * @param callable|callable[] ...$callbacks2 The callback/s to run.
     * @return void
     */
    public static function globalCallbacks(callable|array $callbacks, callable|array ...$callbacks2): void
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseArgs(self::getGlobalCallbacks(), func_get_args());

        self::setGlobalCallbacks($callbacks);
    }



    /**
     * Get the "global" callbacks from global storage.
     *
     * @return callable[]
     */
    private static function getGlobalCallbacks(): mixed
    {
        /** @var callable[] $return */
        $return = Framework::depInj()->get(InternalSettings::CONTAINER_KEY__GLOBAL_CALLBACKS, []);
        return $return;
    }

    /**
     * Set the "global" callbacks in global storage.
     *
     * @param callable[] $callbacks The global callbacks to store.
     * @return void
     */
    private static function setGlobalCallbacks(array $callbacks): void
    {
        Framework::depInj()->set(InternalSettings::CONTAINER_KEY__GLOBAL_CALLBACKS, $callbacks);
    }
}
