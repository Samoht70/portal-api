<?php

use Dailyapps\PortalSync\Http\Controllers\HandleDomainEvent;

Route::post('sync/events', HandleDomainEvent::class);
