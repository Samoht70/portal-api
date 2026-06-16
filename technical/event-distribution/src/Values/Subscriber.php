<?php

namespace Technical\EventDistribution\Values;

/**
 * A subscriber targeted by an event. Will later carry delivery coordinates
 * (endpoint, secret) — out of scope for this increment.
 */
final readonly class Subscriber
{
    public function __construct(
        public string $applicationId,
    ) {}
}
