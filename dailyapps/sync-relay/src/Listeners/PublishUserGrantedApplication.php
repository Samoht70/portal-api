<?php

namespace Dailyapps\SyncRelay\Listeners;

use Functional\Users\Events\UserWasGrantedApplication;
use Illuminate\Support\Facades\Redis;

class PublishUserGrantedApplication
{
    public function handle(UserWasGrantedApplication $event): void
    {
        Redis::publish(
            'sync-relay:user-application:granted',
            json_encode([
                'application_id' => $event->application->getKey(),
                'user' => $event->user->only(['id']),
                'role' => $event->role->name,
            ])
        );
    }
}
