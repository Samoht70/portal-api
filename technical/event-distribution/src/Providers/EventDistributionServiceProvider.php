<?php

namespace Technical\EventDistribution\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

/**
 * Wires the event-distribution layer.
 *
 * This increment only exposes the recipient-resolution contract; the outbox,
 * relay and dispatch jobs land in a later increment.
 */
class EventDistributionServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
    }

    public function register(): void
    {
    }
}
