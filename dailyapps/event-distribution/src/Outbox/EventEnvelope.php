<?php

namespace Dailyapps\EventDistribution\Outbox;

use Dailyapps\EventDistribution\Models\DomainEventRecord;

/**
 * The versioned wire contract between the mother and its child replicas.
 */
final class EventEnvelope
{
    /**
     * Bumped on any breaking change to the wire shape (expand/contract discipline).
     */
    const int SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function fromRecord(DomainEventRecord $record): array
    {
        return [
            'id' => $record->id,
            'sequence' => (int) $record->sequence,
            'aggregate_type' => $record->aggregate_type,
            'aggregate_id' => $record->aggregate_id,
            'event_type' => $record->event_type,
            'tenant_scope' => $record->tenant_scope,
            'occurred_at' => $record->occurred_at->toIso8601String(),
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $record->payload,
        ];
    }
}
