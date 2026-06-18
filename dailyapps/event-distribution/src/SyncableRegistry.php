<?php

namespace Dailyapps\EventDistribution;

class SyncableRegistry
{
    /** @var array<int, class-string> */
    private array $models = [];

    /**
     * @param array $models
     */
    public function push(array $models): void
    {
        foreach ($models as $model) {
            $this->models[] = $model;
        }
    }

    /**
     * @return array<int, class-string>
     */
    public function models(): array
    {
        return $this->models;
    }

    /**
     * The registered model class serving the given aggregate type, or null.
     *
     * @return class-string|null
     */
    public function modelFor(string $aggregateType): ?string
    {
        foreach ($this->models as $model) {
            if ((new $model)->syncAggregateType() === $aggregateType) {
                return $model;
            }
        }

        return null;
    }
}
