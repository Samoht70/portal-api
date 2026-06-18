<?php

namespace Dailyapps\PortalShared\Providers;

use Dailyapps\PortalShared\Console\Commands\BootstrapReplica;
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

        // Registered unconditionally in console (it self-guards on config) so it
        // does not depend on replica mode being set at boot time. Migrations and
        // routing actually act, so they stay gated behind replica mode.
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapReplica::class]);
        }

        if ($this->app->runningInConsole() && config('portal-shared.replica')) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        if (config('portal-shared.replica')) {
            $this->withRouting(api: __DIR__.'/../../routes/api.php');
        }
    }
}
