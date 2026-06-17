<?php

namespace Technical\EventDistribution\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted outbox row in the `domain_events` table.
 */
#[Table('domain_events')]
#[WithoutTimestamps]
#[Fillable(['id', 'aggregate_type', 'aggregate_id', 'event_type', 'payload', 'tenant_scope', 'occurred_at', 'published_at'])]
class DomainEventRecord extends Model
{
    protected $primaryKey = 'sequence';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
