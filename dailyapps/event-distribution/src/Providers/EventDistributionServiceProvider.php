<?php

namespace Dailyapps\EventDistribution\Providers;

use Dailyapps\EventDistribution\Listeners\RecordAggregateDeleted;
use Dailyapps\EventDistribution\Listeners\RecordAggregateUpserted;
use Dailyapps\EventDistribution\SyncableRegistry;
use Xefi\LaravelOSDD\LayerServiceProvider;

/**
 * Wires the event-distribution layer.
 */
class EventDistributionServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SyncableRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->bootOutboxCapture();
        $this->bootRouting();
    }

    private function bootOutboxCapture(): void
    {
        $this->app->booted(function () {
            $models = $this->app->make(SyncableRegistry::class)->models();

            foreach ($models as $model) {
                $model::created(RecordAggregateUpserted::class);
                $model::updated(RecordAggregateUpserted::class);
                $model::deleted(RecordAggregateDeleted::class);
            }
        });
    }

    private function bootRouting(): void
    {
        $this->withRouting(
            commands: __DIR__.'/../../routes/console.php',
        );
    }
}
