<?php

namespace Dailyapps\EventDistribution\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * A model whose state is replicated to subscriber children.
 */
interface SyncableAggregate
{
    /**
     * The aggregate family this model belongs to (e.g. "sites", "clients").
     */
    public function syncAggregateType(): string;

    /**
     * The tenant (client_id) the change is scoped to, or null for global reference data.
     */
    public function syncTenantScope(): ?string;

    /**
     * The full current-state upsert payload to deliver (never a delta, never secrets).
     *
     * @return array<string, mixed>
     */
    public function toSyncPayload(): array;

    /**
     * Query the rows visible to the given subscribed client_ids (the snapshot scope).
     *
     * @param array<int, string> $clientIds
     */
    public static function syncSnapshotQuery(array $clientIds): Builder;
}
