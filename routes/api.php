<?php

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (! app()->runningInConsole()) {
    //    Auth::guard('api')->setUser(User::query()->first());
}
