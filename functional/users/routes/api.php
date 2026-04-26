<?php

use Functional\Users\Rest\Controllers\UsersController;

Route::prefix('api')
    ->middleware(['auth', 'api'])
    ->group(function () {
        Rest::resource('users', UsersController::class);
    });
