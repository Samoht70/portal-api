<?php

namespace Dailyapps\PortalShared\Concerns;

use Dailyapps\PortalShared\Exceptions\ReplicaIsReadOnlyException;

/**
 * Defence-in-depth read-only guard for replica models.
 */
trait ReadOnlyReplica
{
    /**
     * When true, the read-only guard is temporarily lifted.
     */
    protected static bool $replicaGuardDisabled = false;

    /**
     * Register guards on every mutating model event.
     */
    public static function bootReadOnlyReplica(): void
    {
        foreach (['creating', 'updating', 'saving', 'deleting', 'restoring'] as $event) {
            static::registerModelEvent($event, function () {
                if (! static::$replicaGuardDisabled) {
                    throw new ReplicaIsReadOnlyException(static::class.' is a read-only replica and cannot be mutated.');
                }
            });
        }
    }

    /**
     * Run a callback with the read-only guard temporarily disabled.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutReplicaGuard(callable $callback)
    {
        static::$replicaGuardDisabled = true;

        try {
            return $callback();
        } finally {
            static::$replicaGuardDisabled = false;
        }
    }

    /**
     * Replica models expose no fillable attributes by default.
     */
    public function initializeReadOnlyReplica(): void
    {
        $this->fillable = [];
    }
}
