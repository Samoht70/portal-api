<?php

namespace Technical\EventDistribution\Listeners;

use Illuminate\Database\Eloquent\Model;
use Technical\EventDistribution\Contracts\SyncableAggregate;
use Technical\EventDistribution\Outbox\DomainEventRecorder;

/**
 * Records a soft-delete event when a synced aggregate is deleted.
 */
readonly class RecordAggregateDeleted
{
    public function __construct(private DomainEventRecorder $recorder) {}

    public function handle(Model&SyncableAggregate $aggregate): void
    {
        $this->recorder->record($aggregate, 'deleted');
    }
}
