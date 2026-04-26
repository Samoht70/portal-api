<?php

namespace Technical\AccessControl\Providers;

use Technical\AccessControl\Database\Seeders\AccessControlSeeder;
use Technical\AccessControl\Database\Seeders\RolesAndPermissionsSeeder;
use Xefi\LaravelOSDD\LayerServiceProvider;

class AccessControlServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            $this->loadSeeders([
                RolesAndPermissionsSeeder::class,
            ]);
        }

        $this->overrideConfigFrom(__DIR__.'/../../config/permission.php', 'permission');
    }

    public function register(): void
    {
        //
    }
}
