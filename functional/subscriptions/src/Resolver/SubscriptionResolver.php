<?php

namespace Functional\Subscriptions\Resolver;

use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Dailyapps\EventDistribution\Values\Subscriber;

final readonly class SubscriptionResolver implements SubscriberResolver
{
    /**
     * Resolve the subscribers that should receive an event.
     */
    public function resolve(string $aggregateType, ?string $clientId): iterable
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

    /**
     * Resolve one Application's delivery coordinates from its sync endpoint,
     * regardless of its current subscriptions.
     */
    public function resolveApplication(string $applicationId): ?Subscriber
    {
        $endpointApplicationForeignKey = (new ApplicationSyncEndpoint)->application()->getForeignKeyName();

        $endpoint = ApplicationSyncEndpoint::query()
            ->where($endpointApplicationForeignKey, $applicationId)
            ->where('sync_enabled', true)
            ->first();

        if ($endpoint === null) {
            return null;
        }

        return new Subscriber(
            applicationId: $applicationId,
            endpointUrl: $endpoint->endpoint_url,
            secret: $endpoint->secret,
        );
    }
}
