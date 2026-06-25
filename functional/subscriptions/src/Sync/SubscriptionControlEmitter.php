<?php

namespace Functional\Subscriptions\Sync;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Pushes a subscription control event to the affected subscriber, when one exists.
 */
final readonly class SubscriptionControlEmitter
{
    public function __construct(private SyncDirectory $directory) {}

    public function emit(Subscription $subscription, string $eventType): void
    {
        $subscriber = $this->directory->applicationFor($this->applicationId($subscription));

        if ($subscriber === null) {
            return;
        }

        $clientId = $this->clientId($subscription);
        $sequence = (int) (DomainEventRecord::query()->max('sequence') ?? 0);

        $envelope = EventEnvelope::wrap(
            id: (string) Str::uuid7(),
            sequence: $sequence,
            aggregateType: 'subscription',
            aggregateId: $clientId,
            eventType: $eventType,
            tenantScope: $clientId,
            occurredAt: now()->toIso8601String(),
            payload: ['client_id' => $clientId],
        );

        DeliverDomainEvent::dispatch($envelope, $subscriber->endpointUrl, $subscriber->secret);
    }

    private function clientId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->client()->getForeignKeyName());
    }

    private function applicationId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->application()->getForeignKeyName());
    }
}
