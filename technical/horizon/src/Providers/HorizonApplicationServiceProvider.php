<?php

namespace Technical\Horizon\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider as BaseHorizonApplicationServiceProvider;

class HorizonApplicationServiceProvider extends BaseHorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user && $user->can('access horizon');
        });
    }
}
