<?php

use Dailyapps\EventDistribution\Http\Controllers\SyncChecksum;
use Dailyapps\EventDistribution\Http\Controllers\SyncSnapshot;

Route::get('sync/snapshot', SyncSnapshot::class);
Route::get('sync/checksum', SyncChecksum::class);
