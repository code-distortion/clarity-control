<?php

use CodeDistortion\ClarityControl\Settings;

return [

    /*
     |--------------------------------------------------------------------------
     | Reporting (Logging)
     |--------------------------------------------------------------------------
     |
     | Enable or disable reporting. This can also be chosen at call-time. e.g.
     | Control::prepare(..)->report(true/false) or ->dontReport().
     |
     | boolean
     |
     */

    'report' => env('CLARITY_CONTROL__REPORT', true),

    /*
     |--------------------------------------------------------------------------
     | Channels
     |--------------------------------------------------------------------------
     |
     | The channels picked when reporting exceptions. Will fall back to
     | Laravel's default. Can also be chosen at call-time. e.g.
     | Control::prepare(..)->channel('xxx').
     |
     | Control can associate urls to exceptions (making them "known").
     |
     | Note: Used in conjunction with code-distortion/clarity-logger.
     |
     | More info:
     | https://github.com/code-distortion/clarity-control#log-channel
     | https://github.com/code-distortion/clarity-control#recording-known-issues
     | https://laravel.com/docs/10.x/logging#available-channel-drivers
     |
     | string / string[] / null
     | (can be a string of comma-separated values)
     |
     */

    'channels' => [
        'when_known' => env('CLARITY_CONTROL__CHANNELS_WHEN_KNOWN'),
        'when_not_known' => env('CLARITY_CONTROL__CHANNELS_WHEN_NOT_KNOWN'),
     ],

    /*
     |--------------------------------------------------------------------------
     | Reporting Level
     |--------------------------------------------------------------------------
     |
     | The log reporting levels picked when reporting exceptions. Can also be
     | chosen at call-time. e.g. Control::prepare(..)->level('xxx').
     |
     | Control can associate urls to exceptions (making them "known").
     |
     | Note: Used in conjunction with code-distortion/clarity-logger.
     |
     | More info:
     | https://github.com/code-distortion/clarity-control#log-level
     | https://github.com/code-distortion/clarity-control#recording-known-issues
     | https://laravel.com/docs/10.x/logging#writing-log-messages
     |
     | string / null
     |
     */

    'level' => [
        'when_known' => env('CLARITY_CONTROL__LEVEL_WHEN_KNOWN', Settings::REPORTING_LEVEL_ERROR),
        'when_not_known' => env('CLARITY_CONTROL__LEVEL_WHEN_NOT_KNOWN', Settings::REPORTING_LEVEL_ERROR),
    ],

];
