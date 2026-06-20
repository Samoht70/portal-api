<?php

namespace Dailyapps\PortalSync\Console\Commands;

use Dailyapps\PortalSync\Ingestion\ReplicaWriter;
use Dailyapps\PortalSync\Support\MotherSyncClient;
use Illuminate\Console\Command;

/**
 * Seeds the replica by pulling full snapshots from the mother. Each snapshot row
 * carries its `_sync_sequence` (the mother's watermark at snapshot time), which
 * becomes the per-row floor — so a live event with a higher sequence applies and a
 * replay is ignored. No separate watermark call is needed.
 */
class BootstrapReplica extends Command
{
    protected $signature = 'sync:bootstrap';

    protected $description = 'Pull full snapshots from the mother to seed the replica.';

    public function __construct(
        private readonly ReplicaWriter $writer,
        private readonly MotherSyncClient $mother,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('portal-sync.replica')) {
            $this->error('sync:bootstrap only runs on a replica (set PORTAL_SYNC_REPLICA).');

            return self::FAILURE;
        }

        if (! $this->mother->isConfigured()) {
            $this->error('Missing portal-sync.mother_url, application_id or sync_secret configuration.');

            return self::FAILURE;
        }

        foreach (config('portal-sync.snapshot_types', []) as $type) {
            $count = 0;
            $cursor = null;

            do {
                $path = '/api/sync/snapshot?type='.$type;

                if ($cursor !== null) {
                    // Percent-encode the base64 cursor so the bytes we sign match the
                    // URI the mother receives (its getRequestUri keeps it encoded).
                    $path .= '&cursor='.rawurlencode($cursor);
                }

                $page = $this->mother->get($path);

                foreach ($page['data'] as $row) {
                    $this->writer->apply($type, $row);
                    $count++;
                }

                $cursor = $page['next_cursor'] ?? null;
            } while ($cursor !== null);

            $this->info(sprintf('Bootstrapped %d rows for %s.', $count, $type));
        }

        return self::SUCCESS;
    }
}
