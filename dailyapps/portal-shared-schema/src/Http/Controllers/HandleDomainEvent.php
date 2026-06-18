<?php

namespace Dailyapps\PortalShared\Http\Controllers;

use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Dailyapps\PortalShared\Ingestion\ReplicaWriter;
use Dailyapps\PortalShared\Models\ProcessedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Child-side webhook receiver for mother-emitted domain events.
 */
class HandleDomainEvent
{
    public function __construct(
        private readonly ReplicaWriter $writer,
        private readonly ReplicaWatermark $watermark,
    ) {
    }

    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $secret = config('portal-shared.sync_secret');
        $expected = hash_hmac('sha256', $raw, (string) $secret);

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            abort(401);
        }

        $envelope = json_decode($raw, true);
        $eventId = $request->header('X-Event-Id') ?? $envelope['id'];

        if (ProcessedEvent::query()->whereKey($eventId)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        // Discard events at or below the bootstrap watermark, or below the latest
        // sequence already applied for this aggregate.
        $floor = max(
            $this->watermark->get(),
            (int) ProcessedEvent::query()->where('aggregate_id', $envelope['aggregate_id'])->max('sequence'),
        );

        if ((int) $envelope['sequence'] <= $floor) {
            return response()->json(['status' => 'stale']);
        }

        DB::transaction(function () use ($envelope, $eventId) {
            $this->writer->apply($envelope['aggregate_type'], $envelope['payload']);

            ProcessedEvent::query()->create([
                'id' => $eventId,
                'aggregate_type' => $envelope['aggregate_type'],
                'aggregate_id' => $envelope['aggregate_id'],
                'sequence' => $envelope['sequence'],
                'processed_at' => now(),
            ]);
        });

        return response()->json(['status' => 'applied']);
    }
}
