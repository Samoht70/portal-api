<?php

namespace Dailyapps\PortalShared\Providers;

use Dailyapps\PortalShared\Console\Commands\BootstrapReplica;
use Dailyapps\PortalShared\Console\Commands\ReconcileFromMother;
use Dailyapps\PortalShared\Schema\PortalSchema;
use Xefi\LaravelOSDD\LayerServiceProvider;

class PortalSharedServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/portal-shared.php', 'portal-shared');
    }

    public function boot(): void
    {
        PortalSchema::registerMacros();

        // Registered in console regardless of replica mode so registration does
        // not depend on boot-time config; each command refuses to run (FAILURE)
        // unless replica mode and its sync config are set. Migrations and routing
        // actually act, so they stay gated behind replica mode below.
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapReplica::class, ReconcileFromMother::class]);
        }

        if ($this->app->runningInConsole() && config('portal-shared.replica')) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        // Scheduling only makes sense on a child, so the console routes (which
        // schedule sync:reconcile) are gated behind replica mode like the API.
        if (config('portal-shared.replica')) {
            $this->withRouting(
                api: __DIR__.'/../../routes/api.php',
                commands: __DIR__.'/../../routes/console.php',
            );
        }
    }
}
