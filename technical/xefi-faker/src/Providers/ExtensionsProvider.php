<?php

namespace Technical\XefiFaker\Providers;

use Technical\XefiFaker\Database\Faker\Extensions\SampleBackgroundsExtension;
use Technical\XefiFaker\Database\Faker\Extensions\SampleImagesExtension;
use Xefi\Faker\Providers\Provider;

class ExtensionsProvider extends Provider
{
    public function boot(): void
    {
        $this->extensions([
            SampleBackgroundsExtension::class,
            SampleImagesExtension::class,
        ]);
    }
}
