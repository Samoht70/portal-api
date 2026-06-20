<?php

namespace Dailyapps\EventDistribution\Providers;

use Dailyapps\EventDistribution\Listeners\RecordAggregateDeleted;
use Dailyapps\EventDistribution\Listeners\RecordAggregateUpserted;
use Dailyapps\EventDistribution\SyncAggregates;
use Xefi\LaravelOSDD\LayerServiceProvider;

/**
 * Wires the event-distribution layer.
 */
class EventDistributionServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/sync.php', 'sync');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->bootOutboxCapture();
        $this->bootRouting();
    }

    /**
     * Wire create/update/delete capture into the outbox for every model declared in
     * config('sync.aggregates').
     */
    private function bootOutboxCapture(): void
    {
        foreach (SyncAggregates::models() as $model) {
            $model::created(RecordAggregateUpserted::class);
            $model::updated(RecordAggregateUpserted::class);
            $model::deleted(RecordAggregateDeleted::class);
        }
    }

    private function bootRouting(): void
    {
        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
            commands: __DIR__.'/../../routes/console.php',
        );
    }
}
