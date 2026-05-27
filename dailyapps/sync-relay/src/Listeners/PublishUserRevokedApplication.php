<?php

namespace Dailyapps\SyncRelay\Listeners;

use Functional\Users\Events\UserWasRevokedApplication;
use Illuminate\Support\Facades\Redis;

class PublishUserRevokedApplication
{
    public function handle(UserWasRevokedApplication $event): void
    {
        Redis::publish(
            'sync-relay:user-application:revoked',
            json_encode([
                'application_id' => $event->application->getKey(),
                'user' => $event->user->only(['id']),
            ])
        );
    }
}
