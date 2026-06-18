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
        return self::wrap(
            id: $record->id,
            sequence: (int) $record->sequence,
            aggregateType: $record->aggregate_type,
            aggregateId: $record->aggregate_id,
            eventType: $record->event_type,
            tenantScope: $record->tenant_scope,
            occurredAt: $record->occurred_at->toIso8601String(),
            payload: $record->payload,
        );
    }

    /**
     * Assemble an envelope from raw parts — the single source of the wire shape,
     * shared by outbox relay and on-demand backfill.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function wrap(
        string $id,
        int $sequence,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        ?string $tenantScope,
        string $occurredAt,
        array $payload,
    ): array {
        return [
            'id' => $id,
            'sequence' => $sequence,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'tenant_scope' => $tenantScope,
            'occurred_at' => $occurredAt,
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $payload,
        ];
    }
}
