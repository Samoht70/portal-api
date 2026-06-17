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
}
