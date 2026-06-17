<?php

namespace Dailyapps\PortalShared\Providers;

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

        if ($this->app->runningInConsole() && config('portal-shared.replica')) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        if (config('portal-shared.replica')) {
            $this->withRouting(api: __DIR__.'/../../routes/api.php');
        }
    }
}
