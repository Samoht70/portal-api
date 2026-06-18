<?php

namespace Dailyapps\EventDistribution\Http\Controllers;

use Dailyapps\EventDistribution\Concerns\AuthenticatesSyncPull;
use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the highest outbox sequence so a child can decide whether it is caught up.
 */
class SyncWatermark
{
    use AuthenticatesSyncPull;

    public function __construct(
        private readonly SnapshotResolver $resolver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeSyncPull($request, $this->resolver);

        return response()->json([
            'sequence' => (int) (DomainEventRecord::query()->max('sequence') ?? 0),
        ]);
    }
}
