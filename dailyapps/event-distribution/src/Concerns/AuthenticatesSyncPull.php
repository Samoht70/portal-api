<?php

namespace Dailyapps\EventDistribution\Concerns;

use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\Support\HmacSigner;
use Dailyapps\EventDistribution\Values\SnapshotScope;
use Illuminate\Http\Request;

/**
 * Authenticates an inbound sync pull from a child Application via the per-Application
 * HMAC over the request line, resolving the scope it is allowed to read.
 */
trait AuthenticatesSyncPull
{
    protected function authorizeSyncPull(Request $request, SnapshotResolver $resolver): SnapshotScope
    {
        $applicationId = $request->header('X-Application');

        if ($applicationId === null) {
            abort(401);
        }

        $scope = $resolver->authorize($applicationId);

        if ($scope === null) {
            abort(401);
        }

        $expected = app(HmacSigner::class)->sign('GET '.$request->getRequestUri(), $scope->secret);

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            abort(401);
        }

        return $scope;
    }
}
