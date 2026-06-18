<?php

return [
    'replica' => env('PORTAL_SHARED_REPLICA', false),
    'sync_secret' => env('PORTAL_SHARED_SYNC_SECRET'),
    'mother_url' => env('PORTAL_SHARED_MOTHER_URL'),
    'application_id' => env('PORTAL_SHARED_APPLICATION_ID'),
    'snapshot_types' => array_filter(explode(',', (string) env('PORTAL_SHARED_SNAPSHOT_TYPES', ''))),
];
