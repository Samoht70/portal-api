<?php

namespace Dailyapps\EventDistribution\Concerns;

trait SyncsToReplica
{
    public function syncAggregateType(): string
    {
        return $this->getTable();
    }

    public function syncTenantScope(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array
    {
        return $this->attributesToArray();
    }
}
