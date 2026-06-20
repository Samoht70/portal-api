<?php

namespace Dailyapps\PortalSync\Console\Commands;

use Dailyapps\PortalSync\Ingestion\ReplicaWriter;
use Dailyapps\PortalSync\Support\MotherSyncClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles the replica against the mother by comparing per-type fingerprints and
 * pulling the rows that drifted (e.g. after a missed webhook). A full run also
 * tombstones replica rows the mother no longer has.
 *
 * The delta `since` is the replica's own max(updated_at) per type — so no sync-state
 * table is needed: at worst a few already-applied rows are re-pulled, which the
 * conditional upsert makes a no-op.
 */
class ReconcileFromMother extends Command
{
    protected $signature = 'sync:reconcile {--full : Full reconcile with tombstones}';

    protected $description = 'Reconcile the replica against the mother, pulling drifted rows.';

    public function __construct(
        private readonly ReplicaWriter $writer,
        private readonly MotherSyncClient $mother,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('portal-sync.replica')) {
            $this->error('sync:reconcile only runs on a replica (set PORTAL_SYNC_REPLICA).');

            return self::FAILURE;
        }

        if (! $this->mother->isConfigured()) {
            $this->error('Missing portal-sync.mother_url, application_id or sync_secret configuration.');

            return self::FAILURE;
        }

        $full = (bool) $this->option('full');

        foreach (config('portal-sync.snapshot_types', []) as $type) {
            $checksum = $this->mother->get('/api/sync/checksum?type='.$type);

            $motherCount = (int) ($checksum['count'] ?? 0);
            $motherUpdatedAt = $this->normalise($checksum['last_updated_at'] ?? null);

            $localCount = DB::table($type)->whereNull('deleted_at')->count();
            $localUpdatedAt = $this->normalise(DB::table($type)->max('updated_at'));

            if (! $full && $motherCount === $localCount && $motherUpdatedAt === $localUpdatedAt) {
                $this->info(sprintf('%s converged (%d rows).', $type, $localCount));

                continue;
            }

            [$upserted, $motherIds] = $this->pull($type, $full);

            $tombstoned = 0;

            if ($full) {
                $tombstoned = DB::table($type)
                    ->when($motherIds !== [], fn ($query) => $query->whereNotIn('id', $motherIds))
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);
            }

            $this->info(sprintf('%s reconciled (upserted %d, tombstoned %d).', $type, $upserted, $tombstoned));
        }

        return self::SUCCESS;
    }

    /**
     * Page the mother snapshot for a type and upsert every row. A delta run scopes to
     * rows updated at or after the replica's current high-water updated_at.
     *
     * @return array{0: int, 1: list<mixed>} the upsert count and the set of mother ids seen
     */
    private function pull(string $type, bool $full): array
    {
        $upserted = 0;
        $motherIds = [];
        $cursor = null;

        $since = $full ? null : $this->normalise(DB::table($type)->max('updated_at'));

        do {
            $path = '/api/sync/snapshot?type='.$type;

            if ($since !== null) {
                $path .= '&since='.rawurlencode(Carbon::createFromTimestamp($since)->toIso8601String());
            }

            if ($cursor !== null) {
                $path .= '&cursor='.rawurlencode($cursor);
            }

            $page = $this->mother->get($path);

            foreach ($page['data'] as $row) {
                $this->writer->apply($type, $row);
                $motherIds[] = $row['id'];
                $upserted++;
            }

            $cursor = $page['next_cursor'] ?? null;
        } while ($cursor !== null);

        return [$upserted, $motherIds];
    }

    /**
     * Normalise a datetime (ISO8601 from the mother, DB format locally) to a
     * comparable Unix timestamp, or null when absent.
     */
    private function normalise(?string $value): ?int
    {
        return $value === null ? null : Carbon::parse($value)->getTimestamp();
    }
}
