<?php

namespace CodeDistortion\ClarityControl;

/**
 * Common values, shared throughout Clarity Control.
 */
abstract class Settings
{
    /** @var string The possible individual log levels. */
    public const REPORTING_LEVEL_DEBUG = 'debug';
    public const REPORTING_LEVEL_INFO = 'info';
    public const REPORTING_LEVEL_NOTICE = 'notice';
    public const REPORTING_LEVEL_WARNING = 'warning';
    public const REPORTING_LEVEL_ERROR = 'error';
    public const REPORTING_LEVEL_CRITICAL = 'critical';
    public const REPORTING_LEVEL_ALERT = 'alert';
    public const REPORTING_LEVEL_EMERGENCY = 'emergency';

    /** @var string[] The possible log levels */
    public const LOG_LEVELS = [
        self::REPORTING_LEVEL_DEBUG,
        self::REPORTING_LEVEL_INFO,
        self::REPORTING_LEVEL_NOTICE,
        self::REPORTING_LEVEL_WARNING,
        self::REPORTING_LEVEL_ERROR,
        self::REPORTING_LEVEL_CRITICAL,
        self::REPORTING_LEVEL_ALERT,
        self::REPORTING_LEVEL_EMERGENCY,
    ];
}
