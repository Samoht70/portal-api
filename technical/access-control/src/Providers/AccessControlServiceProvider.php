<?php

namespace Technical\AccessControl\Providers;

use Technical\AccessControl\Console\Commands\ControlMakeCommand;
use Technical\AccessControl\Console\Commands\PerimeterMakeCommand;
use Xefi\LaravelOSDD\LayerServiceProvider;

class AccessControlServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            if ($this->app->runningInConsole()) {
                $this->registerCommands();
            }
        }

        $this->overrideConfigFrom(__DIR__.'/../../config/permission.php', 'permission');
    }

    public function register(): void
    {
        //
    }

    private function registerCommands(): void
    {
        $this->commands([
            ControlMakeCommand::class,
            PerimeterMakeCommand::class,
        ]);
    }
}
