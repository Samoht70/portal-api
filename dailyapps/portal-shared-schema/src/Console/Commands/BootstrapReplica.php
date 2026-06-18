<?php

namespace Dailyapps\PortalShared\Console\Commands;

use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Dailyapps\PortalShared\Ingestion\ReplicaWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $motherUrl = config('portal-shared.mother_url');
        $appId = config('portal-shared.application_id');
        $secret = config('portal-shared.sync_secret');

        if (! $motherUrl || ! $appId || ! $secret) {
            $this->error('Missing portal-shared.mother_url, application_id or sync_secret configuration.');

            return self::FAILURE;
        }

        // Record the watermark first so any later event at or below it is ignored.
        $watermark = (int) $this->signedGet($secret, $motherUrl, $appId, '/api/sync/watermark')['sequence'];
        $this->watermark->set($watermark);

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

                $page = $this->signedGet($secret, $motherUrl, $appId, $path);

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

    /**
     * Issue a signed GET against the mother and return the decoded JSON body.
     *
     * @return array<string, mixed>
     */
    private function signedGet(string $secret, string $base, string $appId, string $path): array
    {
        $signature = hash_hmac('sha256', 'GET '.$path, $secret);

        return Http::withHeaders([
            'X-Application' => $appId,
            'X-Signature' => $signature,
        ])
            ->get($base.$path)
            ->throw()
            ->json();
    }
}
