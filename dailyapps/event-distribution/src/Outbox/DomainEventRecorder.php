<?php

namespace Dailyapps\EventDistribution\Outbox;

use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The single sanctioned writer of the outbox table.
 */
class DomainEventRecorder
{
    public function record(Model&SyncableAggregate $aggregate, SyncVerb $verb): void
    {
        DomainEventRecord::query()->create([
            'id' => Str::uuid7(),
            'aggregate_type' => $aggregate->syncAggregateType(),
            'aggregate_id' => $aggregate->getKey(),
            'event_type' => $verb->eventType($aggregate->syncAggregateType()),
            'payload' => $aggregate->toSyncPayload(),
            'tenant_scope' => $aggregate->syncTenantScope(),
            'occurred_at' => now(),
            'published_at' => null,
        ]);
    }
}
