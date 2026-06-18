<?php

namespace Dailyapps\PortalShared\Console\Commands;

use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Dailyapps\PortalShared\Ingestion\ReplicaWriter;
use Dailyapps\PortalShared\Support\MotherSyncClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles the replica against the mother by comparing per-type fingerprints
 * and pulling the rows that drifted (e.g. after a missed webhook). A full run
 * also tombstones replica rows the mother no longer has.
 */
class ReconcileFromMother extends Command
{
    private const string RECONCILE_KEY = 'last_reconcile_at';

    protected $signature = 'sync:reconcile {--full : Full reconcile with tombstones}';

    protected $description = 'Reconcile the replica against the mother, pulling drifted rows.';

    public function __construct(
        private readonly ReplicaWriter $writer,
        private readonly ReplicaWatermark $state,
        private readonly MotherSyncClient $mother,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('portal-shared.replica')) {
            $this->error('sync:reconcile only runs on a replica (set PORTAL_SHARED_REPLICA).');

            return self::FAILURE;
        }

        if (! $this->mother->isConfigured()) {
            $this->error('Missing portal-shared.mother_url, application_id or sync_secret configuration.');

            return self::FAILURE;
        }

        $full = (bool) $this->option('full');
        $lastReconcileAt = $this->state->getValue(self::RECONCILE_KEY);

        foreach (config('portal-shared.snapshot_types', []) as $type) {
            $checksum = $this->mother->get('/api/sync/checksum?type='.$type);

            $motherCount = (int) ($checksum['count'] ?? 0);
            $motherUpdatedAt = $this->normalise($checksum['last_updated_at'] ?? null);

            $localCount = DB::table($type)->whereNull('deleted_at')->count();
            $localUpdatedAt = $this->normalise(DB::table($type)->max('updated_at'));

            if (! $full && $motherCount === $localCount && $motherUpdatedAt === $localUpdatedAt) {
                $this->info(sprintf('%s converged (%d rows).', $type, $localCount));

                continue;
            }

            [$upserted, $motherIds] = $this->pull($type, $full, $lastReconcileAt);

            $tombstoned = 0;

            if ($full) {
                $tombstoned = DB::table($type)
                    ->when($motherIds !== [], fn ($query) => $query->whereNotIn('id', $motherIds))
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);
            }

            $this->info(sprintf('%s reconciled (upserted %d, tombstoned %d).', $type, $upserted, $tombstoned));
        }

        $this->state->setValue(self::RECONCILE_KEY, now()->getTimestamp());

        return self::SUCCESS;
    }

    /**
     * Page the mother snapshot for a type and upsert every row.
     *
     * @return array{0: int, 1: list<mixed>} the upsert count and the set of mother ids seen
     */
    private function pull(string $type, bool $full, int $lastReconcileAt): array
    {
        $upserted = 0;
        $motherIds = [];
        $cursor = null;

        do {
            $path = '/api/sync/snapshot?type='.$type;

            if (! $full && $lastReconcileAt > 0) {
                $path .= '&since='.rawurlencode(Carbon::createFromTimestamp($lastReconcileAt)->toIso8601String());
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
