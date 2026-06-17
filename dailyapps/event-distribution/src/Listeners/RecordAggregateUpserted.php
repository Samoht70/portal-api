<?php

namespace Dailyapps\EventDistribution\Listeners;

use Illuminate\Database\Eloquent\Model;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Dailyapps\EventDistribution\Outbox\DomainEventRecorder;

/**
 * Records a full-state upsert event when a synced aggregate is created or updated.
 */
readonly class RecordAggregateUpserted
{
    public function __construct(private DomainEventRecorder $recorder) {}

    public function handle(Model&SyncableAggregate $aggregate): void
    {
        $this->recorder->record($aggregate, 'upserted');
    }
}
