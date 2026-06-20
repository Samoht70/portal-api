<?php

namespace Dailyapps\PortalSync\Providers;

use Dailyapps\PortalSync\Console\Commands\BootstrapReplica;
use Dailyapps\PortalSync\Console\Commands\ReconcileFromMother;
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
        // Registered in console regardless of replica mode so registration does not
        // depend on boot-time config; each command refuses to run unless replica
        // mode and its sync config are set.
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapReplica::class, ReconcileFromMother::class]);
        }

        // The inbound webhook route and the reconcile schedule only make sense on a
        // child, so they are gated behind replica mode.
        if (config('portal-sync.replica')) {
            $this->withRouting(
                api: __DIR__.'/../../routes/api.php',
                commands: __DIR__.'/../../routes/console.php',
            );
        }
    }
}
