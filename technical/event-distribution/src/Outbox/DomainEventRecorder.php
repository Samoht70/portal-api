<?php

namespace Technical\EventDistribution\Outbox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Technical\EventDistribution\Contracts\SyncableAggregate;
use Technical\EventDistribution\Models\DomainEventRecord;

/**
 * The single sanctioned writer of the outbox table.
 */
class DomainEventRecorder
{
    public function record(Model&SyncableAggregate $aggregate, string $verb): void
    {
        DomainEventRecord::query()->create([
            'id' => Str::uuid7(),
            'aggregate_type' => $aggregate->syncAggregateType(),
            'aggregate_id' => $aggregate->getKey(),
            'event_type' => $aggregate->syncAggregateType().'.'.$verb,
            'payload' => $aggregate->toSyncPayload(),
            'tenant_scope' => $aggregate->syncTenantScope(),
            'occurred_at' => now(),
            'published_at' => null,
        ]);
    }
}
