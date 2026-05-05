<?php

namespace Dailyapps\SyncRelay\Listeners;

use Dailyapps\SyncRelay\Events\ModelWasSaved;
use Illuminate\Support\Facades\Redis;

class PublishModelSaved
{
    public function handle(ModelWasSaved $event): void
    {
        $model = $event->model;

        $model->syncPayload()
            |> json_encode(...)
            |> (fn(string $message) => Redis::publish("sync-relay:{$model->syncResource()}:saved", $message));
    }
}
