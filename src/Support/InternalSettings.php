<?php

namespace CodeDistortion\ClarityControl\Support;

/**
 * Common values, shared throughout Clarity Control.
 */
abstract class InternalSettings
{
    // keys used to store things in the framework's data store

    /** @var string The key used to store the global callbacks inside the framework's service container. */
    public const CONTAINER_KEY__GLOBAL_CALLBACKS = 'code-distortion/clarity-control/global-callbacks';



    // Laravel specific settings

    /** @var string The Clarity Control config file that gets published. */
    public const LARAVEL_CONTROL__CONFIG_PATH = '/config/control.config.php';

    /** @var string The name of the Clarity Control config file. */
    public const LARAVEL_CONTROL__CONFIG_NAME = 'code_distortion.clarity_control';
}
