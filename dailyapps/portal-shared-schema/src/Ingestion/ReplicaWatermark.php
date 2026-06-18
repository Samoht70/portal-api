<?php

namespace Dailyapps\PortalShared\Ingestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and stores the bootstrap watermark used to discard stale events.
 */
class ReplicaWatermark
{
    private const string KEY = 'bootstrap_sequence';

    private const string TABLE = 'replica_sync_state';

    public function get(): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) DB::table(self::TABLE)->where('key', self::KEY)->value('value');
    }

    public function set(int $sequence): void
    {
        DB::table(self::TABLE)->upsert([['key' => self::KEY, 'value' => $sequence]], ['key']);
    }
}
