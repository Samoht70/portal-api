<?php

use Technical\Authentication\Http\Controllers\AuthController;

Route::prefix('api/auth')
    ->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('logout', [AuthController::class, 'logout'])
            ->middleware(['auth', 'api'])
            ->name('auth.logout');

        Route::post('refresh', [AuthController::class, 'refresh'])
            ->middleware(['auth', 'api'])
            ->name('auth.refresh');

        Route::get('me', [AuthController::class, 'me'])
            ->middleware(['auth', 'api'])
            ->name('auth.me');
    });
