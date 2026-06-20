<?php

namespace Dailyapps\EventDistribution\Jobs;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Dailyapps\EventDistribution\Values\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The relay: drains committed-but-unpublished outbox rows in `sequence` order, routes
 * each to its subscribers via the SyncDirectory, dispatches one signed delivery per
 * subscriber, then stamps the row published. Re-dispatches itself while a full batch
 * remains pending (near real-time), and is kicked every minute by the scheduler.
 */
class RelayDomainEvents implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int BATCH = 200;

    public function handle(SyncDirectory $directory): void
    {
        $events = DomainEventRecord::query()
            ->whereNull('published_at')
            ->orderBy('sequence')
            ->limit(self::BATCH)
            ->get();

        foreach ($events as $event) {
            $envelope = EventEnvelope::fromRecord($event);

            foreach ($directory->subscribersFor($event->aggregate_type, $event->tenant_scope) as $subscriber) {
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
