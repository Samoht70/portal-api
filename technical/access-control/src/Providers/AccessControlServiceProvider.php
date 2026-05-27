<?php

namespace Technical\AccessControl\Providers;

use Technical\AccessControl\Console\Commands\ControlMakeCommand;
use Technical\AccessControl\Console\Commands\PerimeterMakeCommand;
use Technical\AccessControl\Database\Seeders\RolesAndPermissionsSeeder;
use Xefi\LaravelOSDD\LayerServiceProvider;

class AccessControlServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            $this->registerCommands();

            $this->loadSeeders([
                RolesAndPermissionsSeeder::class,
            ], 0);
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
