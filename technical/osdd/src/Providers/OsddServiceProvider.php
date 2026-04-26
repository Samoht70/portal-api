<?php

namespace Technical\Osdd\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class OsddServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->overrideConfigFrom(__DIR__ . '/../../config/osdd.php', 'osdd');
    }

    public function register(): void
    {
        //
    }
}
