<?php

use Functional\Organizations\Rest\Controllers\ClientsController;
use Functional\Organizations\Rest\Controllers\SitesController;

Route::prefix('api')
    ->middleware(['auth:api'])
    ->group(function () {
        Rest::resource('clients', ClientsController::class);

        Rest::resource('sites', SitesController::class);
    });
