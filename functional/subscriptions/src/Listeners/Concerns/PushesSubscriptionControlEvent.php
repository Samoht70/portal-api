<?php

namespace Functional\Subscriptions\Listeners\Concerns;

use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Pushes a subscription control event to the affected subscriber, when one exists.
 */
trait PushesSubscriptionControlEvent
{
    use CarriesSubscriptionScope;

    private function pushControlEvent(Subscription $subscription, string $eventType): void
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
}
