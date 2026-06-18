<?php

namespace Functional\Subscriptions\Resolver;

use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Dailyapps\EventDistribution\Contracts\SnapshotResolver;
use Dailyapps\EventDistribution\Values\SnapshotScope;

final readonly class SnapshotScopeResolver implements SnapshotResolver
{
    /**
     * Resolve the pull scope of an authenticated child Application: its signing
     * secret and the client_ids it is subscribed to. Returns null when the
     * Application has no sync endpoint or its sync is disabled.
     */
    public function authorize(string $applicationId): ?SnapshotScope
    {
        $endpointApplicationForeignKey = (new ApplicationSyncEndpoint)->application()->getForeignKeyName();
        $subscriptionApplicationForeignKey = (new Subscription)->application()->getForeignKeyName();
        $subscriptionClientForeignKey = (new Subscription)->client()->getForeignKeyName();

        $endpoint = ApplicationSyncEndpoint::query()
            ->where($endpointApplicationForeignKey, $applicationId)
            ->where('sync_enabled', true)
            ->first();

        if ($endpoint === null) {
            return null;
        }

        $clientIds = Subscription::query()
            ->where($subscriptionApplicationForeignKey, $applicationId)
            ->pluck($subscriptionClientForeignKey);

        return new SnapshotScope(
            secret: $endpoint->secret,
            clientIds: $clientIds->all(),
        );
    }
}
