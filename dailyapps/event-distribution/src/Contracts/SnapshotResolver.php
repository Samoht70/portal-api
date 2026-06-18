<?php

namespace Dailyapps\EventDistribution\Contracts;

use Dailyapps\EventDistribution\Values\SnapshotScope;

/**
 * The inverse of SubscriberResolver: given an authenticated Application id,
 * returns its pull secret and the client_ids it may read, or null when the
 * Application is unknown or its sync is disabled.
 */
interface SnapshotResolver
{
    public function authorize(string $applicationId): ?SnapshotScope;
}
