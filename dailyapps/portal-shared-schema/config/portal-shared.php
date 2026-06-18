<?php

return [
    'replica' => env('PORTAL_SHARED_REPLICA', false),
    'sync_secret' => env('PORTAL_SHARED_SYNC_SECRET'),

    // Reject signed events whose occurred_at is older than this many seconds
    // (anti-replay). 0 disables the window; reconcile backfills any dropped gap.
    'replay_window' => (int) env('PORTAL_SHARED_REPLAY_WINDOW', 300),

    'mother_url' => env('PORTAL_SHARED_MOTHER_URL'),
    'application_id' => env('PORTAL_SHARED_APPLICATION_ID'),
    'snapshot_types' => array_filter(explode(',', (string) env('PORTAL_SHARED_SNAPSHOT_TYPES', ''))),
];
