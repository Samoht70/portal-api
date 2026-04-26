<?php

namespace Technical\Documentation\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class DocumentationServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/scramble.php', 'scramble');
    }

    public function register(): void
    {
        //
    }
}
