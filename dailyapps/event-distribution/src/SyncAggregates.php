<?php

namespace Dailyapps\EventDistribution;

/**
 * Read-only accessor over the `sync.aggregates` config: the wire-type => model-class map
 * that declares which models the sync captures and replicates. The one place that turns
 * an aggregate type from the wire into the model that serves it.
 */
final class SyncAggregates
{
    /**
     * The model classes that participate in sync.
     *
     * @return array<int, class-string>
     */
    public static function models(): array
    {
        return array_values(config('sync.aggregates', []));
    }

    /**
     * The model class serving the given wire aggregate type, or null.
     *
     * @return class-string|null
     */
    public static function modelFor(string $aggregateType): ?string
    {
        return config('sync.aggregates')[$aggregateType] ?? null;
    }
}
