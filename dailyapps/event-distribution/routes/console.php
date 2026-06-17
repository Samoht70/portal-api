<?php

use Dailyapps\EventDistribution\Jobs\RelayDomainEvents;
use Illuminate\Support\Facades\Schedule;

/**
 * Heartbeat that restarts the self-draining relay; the relay itself
 * re-dispatches while a full batch is pending for near real-time delivery.
 */
Schedule::job(new RelayDomainEvents)
    ->everyMinute()
    ->withoutOverlapping();
