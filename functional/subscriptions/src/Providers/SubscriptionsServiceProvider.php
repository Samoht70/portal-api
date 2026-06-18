<?php

namespace Functional\Subscriptions\Providers;

use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Functional\Subscriptions\Access\Controls\SubscriptionControl;
use Functional\Subscriptions\Events\SubscriptionGranted;
use Functional\Subscriptions\Events\SubscriptionRevoked;
use Functional\Subscriptions\Listeners\BackfillOnGrant;
use Functional\Subscriptions\Listeners\PurgeOnRevoke;
use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Policies\SubscriptionPolicy;
use Functional\Subscriptions\Resolver\SnapshotScopeResolver;
use Functional\Subscriptions\Resolver\SubscriptionResolver;
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
        }

        $this->bootRouting();
        $this->registerPolicies();
        $this->registerControls();
        $this->registerSyncListeners();
    }

    public function register(): void
    {
        $this->app->bind(SubscriberResolver::class, SubscriptionResolver::class);
        $this->app->bind(SnapshotResolver::class, SnapshotScopeResolver::class);
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
        // Subscription lifecycle is the "semantic events" carve-out of ARCHITECTURE-SYNC
        // §3.1 — wired explicitly here, NOT through the generic SyncableRegistry capture
        // (a Subscription is not a SyncableAggregate). Do not try to unify the two.
        Event::listen(SubscriptionGranted::class, BackfillOnGrant::class);
        Event::listen(SubscriptionRevoked::class, PurgeOnRevoke::class);
    }
}
