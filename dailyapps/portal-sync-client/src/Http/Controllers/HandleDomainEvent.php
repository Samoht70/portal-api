<?php

namespace Dailyapps\PortalSync\Http\Controllers;

use Dailyapps\PortalSync\Ingestion\ReplicaWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Child-side webhook receiver for mother-emitted domain events.
 *
 * Verifies the HMAC, drops bodies older than the replay window, then either applies
 * a full-state upsert (idempotent + ordered by the writer's `_sync_sequence` gate) or
 * purges a revoked tenant. There is no dedup/watermark bookkeeping: the sequence gate
 * in ReplicaWriter is the whole idempotency story.
 */
class HandleDomainEvent
{
    /**
     * Wire event type for the revoke control signal (kept in step with the mother's
     * PurgeOnRevoke::EVENT_TYPE — see technical/ARCHITECTURE-SYNC.md).
     */
    public const string REVOKE_EVENT_TYPE = 'subscription.revoked';

    public function __construct(private readonly ReplicaWriter $writer) {}

    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, (string) config('portal-sync.sync_secret'));

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            abort(401);
        }

        $envelope = json_decode($raw, true);

        $window = (int) config('portal-sync.replay_window');

        if ($window > 0 && Carbon::parse($envelope['occurred_at'])->lt(now()->subSeconds($window))) {
            return response()->json(['status' => 'expired']);
        }

        if ($envelope['event_type'] === self::REVOKE_EVENT_TYPE) {
            $this->purge((string) $envelope['payload']['client_id']);

            return response()->json(['status' => 'purged']);
        }

        $payload = $envelope['payload'];
        $payload['_sync_sequence'] = (int) $envelope['sequence'];
        $payload['_sync_tenant'] = $envelope['tenant_scope'] ?? null;

        $this->writer->apply($envelope['aggregate_type'], $payload);

        return response()->json(['status' => 'applied']);
    }

    /**
     * Soft-delete every replica row belonging to a revoked tenant. Generic across
     * tables thanks to the `_sync_tenant` column the mother stamps on every row.
     */
    private function purge(string $clientId): void
    {
        foreach (config('portal-sync.snapshot_types', []) as $table) {
            DB::table($table)
                ->where('_sync_tenant', $clientId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }
    }
}
