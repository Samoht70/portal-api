<?php

namespace Functional\Users\Providers;

use Functional\Users\Access\Controls\UserControl;
use Functional\Users\Database\Seeders\UserSeeder;
use Functional\Users\Models\User;
use Functional\Users\Policies\UserPolicy;
use Technical\AccessControl\ControlRegistry;
use Technical\Osdd\GateRegistry;
use Xefi\LaravelOSDD\LayerServiceProvider;

class UsersServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            $this->loadSeeders([
                UserSeeder::class,
            ], 3);
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->app->make(ControlRegistry::class)->push([
            UserControl::new(),
        ]);

        $this->app->make(GateRegistry::class)->push([
            User::class => UserPolicy::class,
        ]);
    }

    public function register(): void
    {
        //
    }
}
