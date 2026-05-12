<?php

namespace Technical\Translations\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class TranslationsServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }

        $this->overrideConfigFrom(__DIR__.'/../../config/translatable.php', 'translatable');
    }

    public function register(): void
    {
        //
    }
}
