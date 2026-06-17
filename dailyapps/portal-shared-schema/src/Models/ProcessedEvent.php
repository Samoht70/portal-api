<?php

namespace Dailyapps\PortalShared\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted dedup marker in the `processed_events` table.
 */
#[Table('processed_events')]
#[WithoutTimestamps]
#[WithoutIncrementing]
#[Fillable(['id', 'aggregate_type', 'aggregate_id', 'sequence', 'processed_at'])]
class ProcessedEvent extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
