<?php

namespace Technical\AccessControl\Console\Commands\Concerns;

use Xefi\LaravelOSDD\Layers\Layer;
use Xefi\LaravelOSDD\Layers\LayersCollection;

use function Laravel\Prompts\search;

trait ChoosesPerimetersLayer
{
    protected ?Layer $resolvedPerimetersLayer = null;

    protected function resolvePerimetersLayer(): Layer
    {
        if ($this->resolvedPerimetersLayer !== null) {
            return $this->resolvedPerimetersLayer;
        }

        $layers = LayersCollection::fromConfig();

        $chosen = search(
            label: 'Which layer should this be generated in?',
            options: fn(string $value) => $layers
                ->filter(fn(Layer $l) => str_contains($l->manifest->name(), $value))
                ->mapWithKeys(fn(Layer $l) => [$l->manifest->name() => $l->manifest->name()])
                ->all(),
        );

        /** @var Layer $layer */
        $layer = $layers->first(fn(Layer $l) => $l->manifest->name() === $chosen);

        return $this->resolvedPerimetersLayer = $layer;
    }
}
