<?php

return [
    // Only a child replica acts on sync: gates the inbound route and the schedule.
    'replica' => env('PORTAL_SYNC_REPLICA', false),

    // Shared HMAC secret for this Application (matches the mother's endpoint secret).
    'sync_secret' => env('PORTAL_SYNC_SECRET'),

    // Reject signed events whose occurred_at is older than this many seconds
    // (anti-replay). 0 disables the window; reconcile backfills any dropped gap.
    'replay_window' => (int) env('PORTAL_SYNC_REPLAY_WINDOW', 300),

    'mother_url' => env('PORTAL_SYNC_MOTHER_URL'),
    'application_id' => env('PORTAL_SYNC_APPLICATION_ID'),

    // The replica tables this child keeps in sync (their names match the wire
    // aggregate types). Used to page snapshots and to purge a revoked tenant.
    'snapshot_types' => array_filter(explode(',', (string) env('PORTAL_SYNC_SNAPSHOT_TYPES', ''))),
];
