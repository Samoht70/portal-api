<?php

use Functional\Subscriptions\Rest\Controllers\SubscriptionsController;

Route::middleware(['auth:api'])
    ->group(function () {
        Rest::resource('subscriptions', SubscriptionsController::class)
            ->only(['details', 'destroy']);
    });
