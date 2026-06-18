<?php

namespace Dailyapps\PortalShared\Ingestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value store over replica_sync_state. Holds the bootstrap watermark used
 * to discard stale events, plus any other replica sync bookkeeping (e.g. the
 * last reconcile timestamp).
 */
class ReplicaWatermark
{
    private const string BOOTSTRAP_KEY = 'bootstrap_sequence';

    private const string TABLE = 'replica_sync_state';

    public function get(): int
    {
        return $this->getValue(self::BOOTSTRAP_KEY);
    }

    public function set(int $sequence): void
    {
        $this->setValue(self::BOOTSTRAP_KEY, $sequence);
    }

    /**
     * Read an arbitrary bigint value by key (0 when absent).
     */
    public function getValue(string $key): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0;
        }

        return (int) DB::table(self::TABLE)->where('key', $key)->value('value');
    }

    /**
     * Upsert an arbitrary bigint value by key.
     */
    public function setValue(string $key, int $value): void
    {
        DB::table(self::TABLE)->upsert([['key' => $key, 'value' => $value]], ['key']);
    }
}
