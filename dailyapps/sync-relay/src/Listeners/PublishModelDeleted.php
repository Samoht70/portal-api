<?php

namespace Dailyapps\SyncRelay\Listeners;

use Dailyapps\SyncRelay\Events\ModelWasDeleted;
use Illuminate\Support\Facades\Redis;

class PublishModelDeleted
{
    public function handle(ModelWasDeleted $event): void
    {
        $model = $event->model;

        Redis::publish(
            "sync-relay:{$model->syncResource()}:deleted",
            json_encode([$model->getKey()])
        );
    }
}
