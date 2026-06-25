<?php

namespace Dailyapps\PortalSync\Jobs;

use Dailyapps\PortalSync\Ingestion\ReplicaWriter;
use Dailyapps\PortalSync\Support\MotherSyncClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pulls a single tenant's current state from the mother into the replica (no since, no tombstone).
 */
class PullTenant implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $clientId) {}

    public function handle(ReplicaWriter $writer, MotherSyncClient $mother): void
    {
        foreach (config('portal-sync.snapshot_types', []) as $type) {
            $cursor = null;

            do {
                $path = '/api/sync/snapshot?type='.rawurlencode($type).'&tenant='.rawurlencode($this->clientId);

                if ($cursor !== null) {
                    $path .= '&cursor='.rawurlencode($cursor);
                }

                $page = $mother->get($path);

                foreach ($page['data'] as $row) {
                    $writer->apply($type, $row);
                }

                $cursor = $page['next_cursor'] ?? null;
            } while ($cursor !== null);
        }
    }
}
