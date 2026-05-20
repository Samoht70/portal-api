<?php

namespace Technical\Rest\Providers;

use Technical\Rest\Console\Commands\ControllerRestCommand;
use Technical\Rest\Console\Commands\ResourceRestCommand;
use Xefi\LaravelOSDD\LayerServiceProvider;

class RestServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    public function register(): void
    {
        //
    }

    private function registerCommands(): void
    {
        $this->commands([
            ResourceRestCommand::class,
            ControllerRestCommand::class,
        ]);
    }
}
