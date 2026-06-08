<?php

use Functional\Users\Rest\Controllers\UsersController;

Route::middleware(['auth', 'api'])
    ->group(function () {
        Rest::resource('users', UsersController::class);
    });
