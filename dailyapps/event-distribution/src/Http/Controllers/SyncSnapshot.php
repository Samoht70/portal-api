<?php

namespace Dailyapps\EventDistribution\Http\Controllers;

use Dailyapps\EventDistribution\Concerns\AuthenticatesSyncPull;
use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Dailyapps\EventDistribution\SyncAggregates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $clientIds = $scope->clientIds;
        $tenant = $request->query('tenant');

        if ($tenant !== null) {
            if (! in_array($tenant, $clientIds, true)) {
                abort(403);
            }

            $clientIds = [$tenant];
        }

        $query = $class::syncSnapshotQuery($clientIds);

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
