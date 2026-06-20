<?php

namespace Dailyapps\EventDistribution\Contracts;

use Dailyapps\EventDistribution\Values\SnapshotScope;
use Dailyapps\EventDistribution\Values\Subscriber;

/**
 * The sync routing directory — the single seam between the transport layer and
 * the functional subscription data. Defined here so the transport depends only on
 * an interface (dependency inversion); functional/subscriptions implements + binds it.
 *
 * It answers the three routing questions the pipeline asks:
 *  - push: which subscribers must receive an event for a tenant?
 *  - pull: what may an authenticated child read, and with which secret?
 *  - control: where does one specific application live (e.g. to notify a revoke)?
 */
interface SyncDirectory
{
    /**
     * The subscribers that must receive an event for the given aggregate type and
     * tenant scope. A null $clientId means a global (untenanted) event → all subscribers.
     *
     * @return iterable<Subscriber>
     */
    public function subscribersFor(string $aggregateType, ?string $clientId): iterable;

    /**
     * The pull scope of an authenticated child Application (signing secret + readable
     * client_ids), or null when the Application is unknown or its sync is disabled.
     */
    public function scopeFor(string $applicationId): ?SnapshotScope;

    /**
     * One Application's delivery coordinates, independent of any subscription — used
     * to notify an application a tenant was revoked (it is no longer a subscriber, so
     * normal routing would not reach it). Null when sync is disabled.
     */
    public function applicationFor(string $applicationId): ?Subscriber;
}
