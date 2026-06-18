<?php

use Dailyapps\EventDistribution\Http\Controllers\SyncChecksum;
use Dailyapps\EventDistribution\Http\Controllers\SyncSnapshot;
use Dailyapps\EventDistribution\Http\Controllers\SyncWatermark;

Route::get('sync/watermark', SyncWatermark::class);
Route::get('sync/snapshot', SyncSnapshot::class);
Route::get('sync/checksum', SyncChecksum::class);
