<?php

namespace Functional\Subscriptions\Resolver;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Values\SnapshotScope;
use Dailyapps\EventDistribution\Values\Subscriber;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;

/**
 * Resolves the sync routing directory from the subscription pivot and each
 * application's sync endpoint. The single implementation of SyncDirectory.
 */
final readonly class SyncDirectoryFromSubscriptions implements SyncDirectory
{
    public function subscribersFor(string $aggregateType, ?string $clientId): iterable
    {
        $clientForeignKey = (new Subscription)->client()->getForeignKeyName();
        $subscriptionApplicationForeignKey = (new Subscription)->application()->getForeignKeyName();
        $endpointApplicationForeignKey = (new ApplicationSyncEndpoint)->application()->getForeignKeyName();

        return Subscription::query()
            ->select([
                'subscriptions.'.$subscriptionApplicationForeignKey.' as application_id',
                'application_sync_endpoints.endpoint_url as endpoint_url',
                'application_sync_endpoints.secret as secret',
            ])
            ->join(
                'application_sync_endpoints',
                'subscriptions.'.$subscriptionApplicationForeignKey,
                'application_sync_endpoints.'.$endpointApplicationForeignKey
            )
            ->where('application_sync_endpoints.sync_enabled', true)
            ->when($clientId !== null, fn ($query) => $query->where('subscriptions.'.$clientForeignKey, $clientId))
            ->distinct()
            ->get()
            ->map(fn ($row) => new Subscriber(
                applicationId: $row->application_id,
                endpointUrl: $row->endpoint_url,
                secret: $row->secret,
            ))
            ->all();
    }

    public function scopeFor(string $applicationId): ?SnapshotScope
    {
        $subscriptionApplicationForeignKey = (new Subscription)->application()->getForeignKeyName();
        $subscriptionClientForeignKey = (new Subscription)->client()->getForeignKeyName();

        $endpoint = $this->enabledEndpointFor($applicationId);

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

    public function applicationFor(string $applicationId): ?Subscriber
    {
        $endpoint = $this->enabledEndpointFor($applicationId);

        if ($endpoint === null) {
            return null;
        }

        return new Subscriber(
            applicationId: $applicationId,
            endpointUrl: $endpoint->endpoint_url,
            secret: $endpoint->secret,
        );
    }

    private function enabledEndpointFor(string $applicationId): ?ApplicationSyncEndpoint
    {
        return ApplicationSyncEndpoint::forApplication($applicationId)->enabled()->first();
    }
}
