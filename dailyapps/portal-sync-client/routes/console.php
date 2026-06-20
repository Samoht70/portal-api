<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Replica reconciliation: an hourly delta to catch missed webhooks quickly and a
 * nightly full pass that also tombstones rows the mother has dropped.
 */
Schedule::command('sync:reconcile')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('sync:reconcile --full')
    ->daily()
    ->withoutOverlapping();
