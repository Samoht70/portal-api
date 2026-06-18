<?php

namespace Functional\Users\Providers;

use Functional\Users\Access\Controls\UserControl;
use Functional\Users\Database\Seeders\UserSeeder;
use Functional\Users\Models\User;
use Functional\Users\Policies\UserPolicy;
use Dailyapps\EventDistribution\SyncableRegistry;
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

        $this->bootRouting();
        $this->registerPolicies();
        $this->registerControls();
        $this->registerSyncables();
    }

    private function bootRouting(): void
    {
        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
        );
    }

    private function registerPolicies(): void
    {
        $this->app->make(GateRegistry::class)->push([
            User::class => UserPolicy::class,
        ]);
    }

    private function registerControls(): void
    {
        $this->app->make(ControlRegistry::class)->push([
            UserControl::new(),
        ]);
    }

    private function registerSyncables(): void
    {
        $this->app->make(SyncableRegistry::class)->push([
            User::class,
        ]);
    }
}
