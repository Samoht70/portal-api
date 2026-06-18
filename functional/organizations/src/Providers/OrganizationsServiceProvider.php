<?php

namespace Functional\Organizations\Providers;

use Functional\Organizations\Access\Controls\ClientControl;
use Functional\Organizations\Access\Controls\SiteControl;
use Functional\Organizations\Database\Seeders\ClientSeeder;
use Functional\Organizations\Database\Seeders\SiteSeeder;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Organizations\Policies\ClientPolicy;
use Functional\Organizations\Policies\SitePolicy;
use Technical\AccessControl\ControlRegistry;
use Dailyapps\EventDistribution\SyncableRegistry;
use Technical\Osdd\GateRegistry;
use Xefi\LaravelOSDD\LayerServiceProvider;

class OrganizationsServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            $this->loadSeeders([
                ClientSeeder::class,
                SiteSeeder::class,
            ], 2);
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
            Client::class => ClientPolicy::class,
            Site::class => SitePolicy::class,
        ]);
    }

    private function registerControls(): void
    {
        $this->app->make(ControlRegistry::class)->push([
            ClientControl::new(),
            SiteControl::new(),
        ]);
    }

    private function registerSyncables(): void
    {
        $this->app->make(SyncableRegistry::class)->push([
            Client::class,
            Site::class,
        ]);
    }
}
