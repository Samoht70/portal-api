<?php

namespace Dailyapps\EventDistribution\Values;

/**
 * A subscriber targeted by an event, carrying the delivery coordinates the
 * transport needs to push a signed webhook: where to POST and which secret to
 * sign the body with. Resolved by the functional layer that owns subscriptions.
 */
final readonly class Subscriber
{
    public function __construct(
        public string $applicationId,
        public string $endpointUrl,
        public string $secret,
    ) {}
}
