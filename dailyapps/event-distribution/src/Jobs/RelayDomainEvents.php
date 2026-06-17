<?php

namespace Dailyapps\EventDistribution\Jobs;

use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Dailyapps\EventDistribution\Values\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The relay: drains committed-but-unpublished outbox rows in `sequence` order,
 * routes each to its subscribers and fans out one signed delivery per subscriber,
 * then stamps the row as published.
 */
class RelayDomainEvents implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int BATCH = 200;

    public function handle(SubscriberResolver $resolver): void
    {
        $events = DomainEventRecord::query()
            ->whereNull('published_at')
            ->orderBy('sequence')
            ->limit(self::BATCH)
            ->get();

        foreach ($events as $event) {
            $envelope = EventEnvelope::fromRecord($event);

            foreach ($resolver->resolve($event->aggregate_type, $event->tenant_scope) as $subscriber) {
                /** @var Subscriber $subscriber */
                DeliverDomainEvent::dispatch($envelope, $subscriber->endpointUrl, $subscriber->secret);
            }

            $event->update(['published_at' => now()]);
        }

        if ($events->count() === self::BATCH) {
            self::dispatch()->delay(now()->addSeconds(2));
        }
    }
}
