<?php

namespace CodeDistortion\ClarityControl;

use CodeDistortion\ClarityControl\Support\InternalSettings;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Clarity Control's Laravel ServiceProvider.
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Service-provider register method.
     *
     * @return void
     */
    public function register(): void
    {
        $this->initialiseConfig();
    }

    /**
     * Service-provider boot method.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishConfig();
    }



    /**
     * Initialise the config settings file.
     *
     * @return void
     */
    private function initialiseConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/..' . InternalSettings::LARAVEL_CONTROL__CONFIG_PATH,
            InternalSettings::LARAVEL_CONTROL__CONFIG_NAME
        );
    }

    /**
     * Allow the default config to be published.
     *
     * @return void
     */
    private function publishConfig(): void
    {
        $src = __DIR__ . '/..' . InternalSettings::LARAVEL_CONTROL__CONFIG_PATH;
        $dest = config_path(InternalSettings::LARAVEL_CONTROL__CONFIG_NAME . '.php');

        $this->publishes([$src => $dest], 'config');
    }
}
