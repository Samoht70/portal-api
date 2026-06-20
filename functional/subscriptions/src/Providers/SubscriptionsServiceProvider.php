<?php

namespace Functional\Subscriptions\Providers;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Functional\Subscriptions\Access\Controls\SubscriptionControl;
use Functional\Subscriptions\Console\Commands\LinkSyncSubscriber;
use Functional\Subscriptions\Events\SubscriptionRevoked;
use Functional\Subscriptions\Listeners\PurgeOnRevoke;
use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Policies\SubscriptionPolicy;
use Functional\Subscriptions\Resolver\SyncDirectoryFromSubscriptions;
use Illuminate\Support\Facades\Event;
use Technical\AccessControl\ControlRegistry;
use Technical\Osdd\GateRegistry;
use Xefi\LaravelOSDD\LayerServiceProvider;

class SubscriptionsServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

            $this->commands([
                LinkSyncSubscriber::class
            ]);
        }

        $this->bootRouting();
        $this->registerPolicies();
        $this->registerControls();
        $this->registerSyncListeners();
    }

    public function register(): void
    {
        $this->app->bind(SyncDirectory::class, SyncDirectoryFromSubscriptions::class);
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
            Subscription::class => SubscriptionPolicy::class,
        ]);
    }

    private function registerControls(): void
    {
        $this->app->make(ControlRegistry::class)->push([
            SubscriptionControl::new(),
        ]);
    }

    private function registerSyncListeners(): void
    {
        Event::listen(SubscriptionRevoked::class, PurgeOnRevoke::class);
    }
}
