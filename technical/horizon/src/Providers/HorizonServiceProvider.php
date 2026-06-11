<?php

namespace Technical\Horizon\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class HorizonServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/horizon.php', 'horizon');
    }
}
