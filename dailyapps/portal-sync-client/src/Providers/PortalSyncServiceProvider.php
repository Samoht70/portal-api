<?php

namespace Dailyapps\PortalSync\Providers;

use Dailyapps\PortalSync\Console\Commands\BootstrapReplica;
use Dailyapps\PortalSync\Console\Commands\ReconcileFromMother;
use Dailyapps\PortalSync\Database\Seeders\CoreReplicaSeeder;
use Xefi\LaravelOSDD\LayerServiceProvider;

/**
 * Wires the child sync-ingestion layer: the bootstrap/reconcile console commands, and —
 * only in replica mode — the inbound webhook route and the reconcile schedule.
 */
class PortalSyncServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/portal-sync.php', 'portal-sync');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapReplica::class,
                ReconcileFromMother::class,
            ]);
        }

        if (config('portal-sync.replica')) {
            $this->withRouting(
                api: __DIR__.'/../../routes/api.php',
                commands: __DIR__.'/../../routes/console.php',
            );
        }
    }
}
