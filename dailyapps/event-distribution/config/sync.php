<?php

use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;

return [
    /*
     * The identity/org-core aggregates replicated to child apps, keyed by the wire
     * aggregate type (the table name carried in the event type and on the sync
     * endpoints). This single list is the whole capture surface: listing a model
     * here both wires its create/update/delete capture into the outbox and lets the
     * snapshot/checksum endpoints resolve the model behind an aggregate type.
     *
     * It deliberately lives in one readable place rather than being pushed at boot
     * from each layer — adding an aggregate to the sync is a one-line edit here.
     */
    'aggregates' => [
        'clients' => Client::class,
        'sites' => Site::class,
        'users' => User::class,
    ],
];
