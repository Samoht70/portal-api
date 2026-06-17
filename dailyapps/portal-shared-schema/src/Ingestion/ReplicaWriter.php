<?php

namespace Dailyapps\PortalShared\Ingestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single sanctioned writer of replica tables.
 */
class ReplicaWriter
{
    public function apply(string $table, array $payload): void
    {
        $columns = Schema::getColumnListing($table);

        $filtered = array_intersect_key($payload, array_flip($columns));

        DB::table($table)->upsert([$filtered], ['id']);
    }
}
