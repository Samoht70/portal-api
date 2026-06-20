<?php

namespace Functional\Subscriptions\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Functional\Subscriptions\Events\SubscriptionRevoked;
use Illuminate\Support\Str;

/**
 * On a revoked subscription, sends the (now-unsubscribed) application a single
 * `subscription.revoked` control event so it purges that tenant from its replica.
 * Resolves the endpoint directly via SyncDirectory::applicationFor(), since normal
 * routing no longer reaches an app that is no longer a subscriber.
 */
final readonly class PurgeOnRevoke
{
    public const string EVENT_TYPE = 'subscription.revoked';

    public function __construct(private SyncDirectory $directory) {}

    public function handle(SubscriptionRevoked $event): void
    {
        $subscriber = $this->directory->applicationFor($event->applicationId());

        if ($subscriber === null) {
            return;
        }

        $clientId = $event->clientId();
        $sequence = (int) (DomainEventRecord::query()->max('sequence') ?? 0);

        $envelope = EventEnvelope::wrap(
            id: (string) Str::uuid7(),
            sequence: $sequence,
            aggregateType: 'subscription',
            aggregateId: $clientId,
            eventType: self::EVENT_TYPE,
            tenantScope: $clientId,
            occurredAt: now()->toIso8601String(),
            payload: ['client_id' => $clientId],
        );

        DeliverDomainEvent::dispatch($envelope, $subscriber->endpointUrl, $subscriber->secret);
    }
}
