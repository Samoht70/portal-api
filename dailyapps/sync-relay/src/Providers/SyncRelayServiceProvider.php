<?php

namespace Dailyapps\SyncRelay\Providers;

use Dailyapps\SyncRelay\Events\ModelWasDeleted;
use Dailyapps\SyncRelay\Events\ModelWasSaved;
use Dailyapps\SyncRelay\Listeners\PublishModelDeleted;
use Dailyapps\SyncRelay\Listeners\PublishModelSaved;
use Dailyapps\SyncRelay\Listeners\PublishUserGrantedApplication;
use Dailyapps\SyncRelay\Listeners\PublishUserRevokedApplication;
use Functional\Users\Events\UserWasGrantedApplication;
use Functional\Users\Events\UserWasRevokedApplication;
use Illuminate\Support\Facades\Event;
use Xefi\LaravelOSDD\LayerServiceProvider;

class SyncRelayServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        Event::listen(ModelWasSaved::class, PublishModelSaved::class);
        Event::listen(ModelWasDeleted::class, PublishModelDeleted::class);

        Event::listen(UserWasGrantedApplication::class, PublishUserGrantedApplication::class);
        Event::listen(UserWasRevokedApplication::class, PublishUserRevokedApplication::class);
    }

    public function register(): void
    {
        //
    }
}
