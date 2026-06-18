<?php

namespace Dailyapps\PortalShared\Console\Commands;

use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Dailyapps\PortalShared\Ingestion\ReplicaWriter;
use Dailyapps\PortalShared\Support\MotherSyncClient;
use Illuminate\Console\Command;

/**
 * Bootstraps the replica by pulling the mother watermark and full snapshots.
 */
class BootstrapReplica extends Command
{
    protected $signature = 'sync:bootstrap';

    protected $description = 'Pull the mother watermark and full snapshots to seed the replica.';

    public function __construct(
        private readonly ReplicaWriter $writer,
        private readonly ReplicaWatermark $watermark,
        private readonly MotherSyncClient $mother,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('portal-shared.replica')) {
            $this->error('sync:bootstrap only runs on a replica (set PORTAL_SHARED_REPLICA).');

            return self::FAILURE;
        }

        if (! $this->mother->isConfigured()) {
            $this->error('Missing portal-shared.mother_url, application_id or sync_secret configuration.');

            return self::FAILURE;
        }

        // Record the watermark first so any later event at or below it is ignored.
        $this->watermark->set((int) $this->mother->get('/api/sync/watermark')['sequence']);

        foreach (config('portal-shared.snapshot_types', []) as $type) {
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
