<?php

namespace Dailyapps\EventDistribution\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;

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

    /**
     * @param array<int, string> $clientIds
     */
    public static function syncSnapshotQuery(array $clientIds): Builder
    {
        return static::query()->whereIn('client_id', $clientIds);
    }
}
