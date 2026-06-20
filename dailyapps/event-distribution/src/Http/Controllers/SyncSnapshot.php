<?php

namespace Dailyapps\EventDistribution\Http\Controllers;

use Dailyapps\EventDistribution\Concerns\AuthenticatesSyncPull;
use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\SyncAggregates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves a cursor-paginated snapshot of an aggregate type, scoped to the rows the
 * authenticated child Application is allowed to read. Each row is stamped with
 * `_sync_sequence` (the current outbox watermark) and `_sync_tenant`, which seed the
 * child's per-row sync floor so live events and replays order correctly after bootstrap.
 */
class SyncSnapshot
{
    use AuthenticatesSyncPull;

    public function __construct(
        private readonly SyncDirectory $directory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $scope = $this->authorizeSyncPull($request, $this->directory);

        $class = SyncAggregates::modelFor((string) $request->query('type'));

        if ($class === null) {
            abort(422);
        }

        $query = $class::syncSnapshotQuery($scope->clientIds);

        $since = $request->query('since');

        if ($since !== null) {
            $query = $query->where('updated_at', '>=', $since);
        }

        $watermark = (int) (DomainEventRecord::query()->max('sequence') ?? 0);

        $paginator = $query
            ->orderBy('id')
            ->cursorPaginate(100);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn ($item) => $item->toSyncPayload() + [
                    '_sync_sequence' => $watermark,
                    '_sync_tenant' => $item->syncTenantScope(),
                ]),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }
}
