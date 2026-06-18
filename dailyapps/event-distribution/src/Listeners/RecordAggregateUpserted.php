<?php

namespace Dailyapps\EventDistribution\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Dailyapps\EventDistribution\Outbox\DomainEventRecorder;
use Dailyapps\EventDistribution\Outbox\SyncVerb;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a full-state upsert event when a synced aggregate is created or updated.
 */
readonly class RecordAggregateUpserted
{
    public function __construct(private DomainEventRecorder $recorder) {}

    public function handle(Model&SyncableAggregate $aggregate): void
    {
        $this->recorder->record($aggregate, SyncVerb::Upserted);
    }
}
