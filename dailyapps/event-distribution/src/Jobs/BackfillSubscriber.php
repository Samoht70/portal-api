<?php

namespace Dailyapps\EventDistribution\Jobs;

use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Dailyapps\EventDistribution\Outbox\SyncVerb;
use Dailyapps\EventDistribution\SyncableRegistry;
use Dailyapps\EventDistribution\Values\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Catches one subscriber up on a tenant's current state by re-delivering each of
 * that tenant's syncable aggregates as a signed upsert. With $tombstone, the same
 * rows are delivered carrying a deleted_at so the child soft-deletes them (purge
 * on subscription revocation). Reuses the live delivery + idempotency pipeline.
 */
#[Tries(3)]
#[Backoff(10, 30)]
class BackfillSubscriber implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly Subscriber $subscriber,
        private readonly string $clientId,
        private readonly bool $tombstone,
    ) {
        $this->onQueue('sync');
    }

    public function handle(SyncableRegistry $registry): void
    {
        $sequence = (int) (DomainEventRecord::query()->max('sequence') ?? 0);

        foreach ($registry->models() as $model) {
            foreach ($model::syncSnapshotQuery([$this->clientId])->cursor() as $row) {
                DeliverDomainEvent::dispatch(
                    $this->envelopeFor($row, $sequence),
                    $this->subscriber->endpointUrl,
                    $this->subscriber->secret,
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function envelopeFor(Model&SyncableAggregate $row, int $sequence): array
    {
        $now = now();
        $type = $row->syncAggregateType();
        $verb = $this->tombstone ? SyncVerb::Deleted : SyncVerb::Upserted;
        $payload = $row->toSyncPayload();

        if ($this->tombstone) {
            $payload['deleted_at'] = $now->toDateTimeString();
        }

        return EventEnvelope::wrap(
            id: (string) Str::uuid(),
            sequence: $sequence,
            aggregateType: $type,
            aggregateId: $row->getKey(),
            eventType: $verb->eventType($type),
            tenantScope: $this->clientId,
            occurredAt: $now->toIso8601String(),
            payload: $payload,
        );
    }
}
