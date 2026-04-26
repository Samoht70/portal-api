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
            ], 1);
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->app->make(ControlRegistry::class)->push([
            ClientControl::new(),
            SiteControl::new(),
        ]);

        $this->app->make(GateRegistry::class)->push([
            Client::class => ClientPolicy::class,
            Site::class => SitePolicy::class,
        ]);
    }

    public function register(): void
    {
        //
    }
}
