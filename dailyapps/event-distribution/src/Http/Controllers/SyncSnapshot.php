<?php

namespace Dailyapps\EventDistribution\Http\Controllers;

use Dailyapps\EventDistribution\Concerns\AuthenticatesSyncPull;
use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\SyncableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves a cursor-paginated snapshot of an aggregate type, scoped to the rows the
 * authenticated child Application is allowed to read.
 */
class SyncSnapshot
{
    use AuthenticatesSyncPull;

    public function __construct(
        private readonly SnapshotResolver $resolver,
        private readonly SyncableRegistry $registry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $scope = $this->authorizeSyncPull($request, $this->resolver);

        $class = $this->registry->modelFor((string) $request->query('type'));

        if ($class === null) {
            abort(422);
        }

        $query = $class::syncSnapshotQuery($scope->clientIds);

        $since = $request->query('since');

        if ($since !== null) {
            $query = $query->where('updated_at', '>=', $since);
        }

        $paginator = $query
            ->orderBy('id')
            ->cursorPaginate(100);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item) => $item->toSyncPayload()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }
}
