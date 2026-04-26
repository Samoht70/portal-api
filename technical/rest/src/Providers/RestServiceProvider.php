<?php

namespace Technical\Rest\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class RestServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        //
    }
}
