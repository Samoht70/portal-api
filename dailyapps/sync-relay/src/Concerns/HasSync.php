<?php

namespace Dailyapps\SyncRelay\Concerns;

use Dailyapps\SyncRelay\Events\ModelWasDeleted;
use Dailyapps\SyncRelay\Events\ModelWasSaved;
use Illuminate\Support\Str;

trait HasSync
{
    public static function bootHasSync(): void
    {
        static::updated(function (self $model) {
            $model->loadMissing($model->syncWith());

            event(new ModelWasSaved($model));
        });

        static::deleted(fn(self $model) => event(new ModelWasDeleted($model)));
    }

    public function syncResource(): string
    {
        return Str::snake(class_basename($this));
    }

    public function syncPayload(): array
    {
        return $this->toArray();
    }

    public function syncWith(): array
    {
        return [];
    }
}
