<?php

namespace Technical\AccessControl\Providers;

use Technical\AccessControl\Access\Controls\RoleControl;
use Technical\AccessControl\Console\Commands\ControlMakeCommand;
use Technical\AccessControl\Console\Commands\PerimeterMakeCommand;
use Technical\AccessControl\ControlRegistry;
use Technical\AccessControl\Database\Seeders\RolesAndPermissionsSeeder;
use Technical\AccessControl\Models\Role;
use Technical\AccessControl\Policies\RolePolicy;
use Technical\Osdd\GateRegistry;
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

        $this->registerPolicies();
        $this->registerControls();
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/permission.php', 'permission');
    }

    private function registerCommands(): void
    {
        $this->commands([
            ControlMakeCommand::class,
            PerimeterMakeCommand::class,
        ]);
    }

    private function registerPolicies(): void
    {
        $this->app->make(GateRegistry::class)->push([
            Role::class => RolePolicy::class,
        ]);
    }

    private function registerControls(): void
    {
        $this->app->make(ControlRegistry::class)->push([
            RoleControl::new(),
        ]);
    }
}
