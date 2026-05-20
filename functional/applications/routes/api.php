<?php

use Functional\Applications\Rest\Controllers\ApplicationsController;
use Functional\Applications\Rest\Controllers\PacksController;

Route::prefix('api')
    ->middleware(['auth:api'])
    ->group(function () {
        Rest::resource('packs', PacksController::class)
            ->only(['details', 'search']);

        Rest::resource('applications', ApplicationsController::class)
            ->only(['details', 'search']);
    });
