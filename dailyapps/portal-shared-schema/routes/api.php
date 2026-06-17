<?php

use Dailyapps\PortalShared\Http\Controllers\HandleDomainEvent;

Route::post('sync/events', HandleDomainEvent::class);
