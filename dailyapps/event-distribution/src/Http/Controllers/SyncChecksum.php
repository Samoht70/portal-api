<?php

namespace Dailyapps\EventDistribution\Http\Controllers;

use Dailyapps\EventDistribution\Concerns\AuthenticatesSyncPull;
use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\SyncableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Returns a cheap checksum (row count and max updated_at) of an aggregate type,
 * scoped to the rows the authenticated child Application is allowed to read, so a
 * child can detect drift before pulling a full snapshot.
 */
class SyncChecksum
{
    use AuthenticatesSyncPull;

    public function __construct(
        private readonly SnapshotResolver $resolver,
        private readonly SyncableRegistry $registry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $scope = $this->authorizeSyncPull($request, $this->resolver);

        $type = (string) $request->query('type');
        $class = $this->registry->modelFor($type);

        if ($class === null) {
            abort(422);
        }

        $query = $class::syncSnapshotQuery($scope->clientIds);

        $max = (clone $query)->max('updated_at');

        return response()->json([
            'type' => $type,
            'count' => (clone $query)->count(),
            'last_updated_at' => $max ? Carbon::parse($max)->toIso8601String() : null,
        ]);
    }
}
