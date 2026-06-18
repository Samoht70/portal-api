<?php

namespace Dailyapps\EventDistribution\Values;

/**
 * The pull scope granted to an authenticated child Application: the secret its
 * requests must be signed with, and the client_ids whose rows it may read.
 */
final readonly class SnapshotScope
{
    /**
     * @param array<int, string> $clientIds
     */
    public function __construct(
        public string $secret,
        public array $clientIds,
    ) {}
}
