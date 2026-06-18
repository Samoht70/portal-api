<?php

namespace Dailyapps\EventDistribution\Outbox;

/**
 * The wire vocabulary for an aggregate change. The single source of the verbs
 * and the "<aggregateType>.<verb>" event-type format, shared by the outbox
 * recorder and on-demand backfill so the two paths cannot drift.
 */
enum SyncVerb: string
{
    case Upserted = 'upserted';
    case Deleted = 'deleted';

    public function eventType(string $aggregateType): string
    {
        return $aggregateType.'.'.$this->value;
    }
}
