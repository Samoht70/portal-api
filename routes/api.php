<?php

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Auth;

if (! app()->runningInConsole()) {
//    Auth::guard('api')
//        ->setUser(User::query()->first());
}
