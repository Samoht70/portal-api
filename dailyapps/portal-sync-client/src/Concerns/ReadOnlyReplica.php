<?php

namespace Dailyapps\PortalSync\Concerns;

use Dailyapps\PortalSync\Exceptions\ReplicaIsReadOnlyException;

/**
 * Defence-in-depth guard for replica models that the child also extends with its own
 * LOCAL columns (e.g. a child User with extra fields).
 *
 * Sync is the only writer of the mother-owned columns — and it writes via DB::table(),
 * never through the model, so this guard never sees the ingestion path. It exists to
 * stop the child's own code from hand-editing mother-owned columns through the model:
 * saving is rejected only when a *synced* column is dirty (and the lifecycle is owned by
 * sync, so deletes/restores are rejected too). Changes that touch only local columns
 * pass through untouched.
 */
trait ReadOnlyReplica
{
    /**
     * When true, the guard is temporarily lifted (sanctioned writes, seeding, tests).
     */
    protected static bool $replicaGuardDisabled = false;

    /**
     * The mother-owned columns this model must not be edited through. Local columns the
     * child added are NOT listed here and stay freely writable.
     *
     * @return array<int, string>
     */
    abstract public function syncedColumns(): array;

    public static function bootReadOnlyReplica(): void
    {
        static::saving(function ($model) {
            if (static::$replicaGuardDisabled) {
                return;
            }

            $editedSynced = array_intersect(array_keys($model->getDirty()), $model->syncedColumns());

            if ($editedSynced !== []) {
                throw new ReplicaIsReadOnlyException(
                    static::class.' may not edit sync-owned columns ['.implode(', ', $editedSynced).']; they are written by sync only.'
                );
            }
        });

        foreach (['deleting', 'restoring'] as $event) {
            static::registerModelEvent($event, function () {
                if (! static::$replicaGuardDisabled) {
                    throw new ReplicaIsReadOnlyException(static::class.' lifecycle is owned by sync; local code may not delete or restore it.');
                }
            });
        }
    }

    /**
     * Run a callback with the guard temporarily disabled.
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
}
