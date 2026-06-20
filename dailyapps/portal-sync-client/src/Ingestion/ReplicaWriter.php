<?php

namespace Dailyapps\PortalSync\Ingestion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * The single sanctioned writer of replica tables (MySQL).
 *
 * Performs an atomic conditional upsert: a row is written only when the incoming
 * `_sync_sequence` is strictly greater than the stored one — so replays and out-of-order
 * events are no-ops without any dedup/watermark table. Only the columns present in the
 * payload are written, so local columns the child added are left untouched.
 */
class ReplicaWriter
{
    /**
     * Per-table column listing, memoised for the writer's lifetime so a bootstrap
     * loop over many rows doesn't re-introspect the schema on every row.
     *
     * @var array<string, array<int, string>>
     */
    private array $columnsByTable = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(string $table, array $payload): void
    {
        $columns = $this->columnsByTable[$table] ??= Schema::getColumnListing($table);
        $row = array_intersect_key($payload, array_flip($columns));

        foreach (['id', '_sync_sequence'] as $required) {
            if (! array_key_exists($required, $row)) {
                throw new LogicException("Replica table `{$table}` is missing the required `{$required}` column.");
            }
        }

        $names = array_keys($row);

        $insertColumns = $names
            |> (fn ($cols) => array_map(fn ($c) => "`{$c}`", $cols))
            |> (fn ($cols) => implode(', ', $cols));

        $placeholders = $names
            |> (fn ($cols) => array_fill(0, count($cols), '?'))
            |> (fn ($parts) => implode(', ', $parts));

        $set = $names
            |> (fn ($cols) => array_filter($cols, fn ($c) => $c !== 'id'))
            |> (fn ($cols) => array_map(
                fn ($c) => "`{$c}` = if(values(`_sync_sequence`) > `_sync_sequence`, values(`{$c}`), `{$c}`)",
                $cols,
            ))
            |> (fn ($clauses) => implode(', ', $clauses));

        DB::statement(
            "insert into `{$table}` ({$insertColumns}) values ({$placeholders}) on duplicate key update {$set}",
            array_values($row),
        );
    }
}
