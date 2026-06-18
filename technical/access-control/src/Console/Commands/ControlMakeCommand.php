<?php

namespace Technical\AccessControl\Console\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Access\Console\ControlMakeCommand as BaseControlMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;
use Technical\AccessControl\Console\Commands\Concerns\ChoosesPerimetersLayer;
use Xefi\LaravelOSDD\Console\Commands\Make\ChoosesOsddLayer;

#[AsCommand(name: 'osdd:control')]
class ControlMakeCommand extends BaseControlMakeCommand
{
    use ChoosesOsddLayer;
    use ChoosesPerimetersLayer;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'osdd:control';

    protected function rootNamespace(): string
    {
        return $this->resolveLayer()->manifest->rootNamespace();
    }

    protected function perimeterRootNamespace(): string
    {
        return $this->resolvePerimetersLayer()->manifest->rootNamespace();
    }

    protected function getPath($name): string
    {
        $layer = $this->resolveLayer();
        $layerNamespace = rtrim($layer->manifest->rootNamespace(), '\\');

        $relative = Str::replaceFirst($layerNamespace.'\\Access\\Controls\\', '', $name);

        return $layer->path.'/src/Access/Controls/'.str_replace('\\', '/', $relative).'.php';
    }

    protected function buildPerimetersReplacements(array $replace, array $perimeters): array
    {
        $perimetersImplementation = '';

        foreach ($perimeters as $perimeter) {
            $perimeterClass = '\\'.$this->perimeterRootNamespace().'Access\\Perimeters\\'.$perimeter;

            $perimetersImplementation .= <<<PERIMETER
            $perimeterClass::new()
                    ->allowed(function (Model \$user, string \$method) {
                        return true;
                    })
                    ->should(function (Model \$user, Model \$model) {
                        return true;
                    })
                    ->query(function (Builder \$query, Model \$user) {
                        return \$query;
                    }),

            PERIMETER;
        }

        return array_merge($replace, [
            '{{ perimeters }}' => $perimetersImplementation,
            '{{perimeters}}' => $perimetersImplementation,
        ]);
    }

    protected function possiblePerimeters()
    {
        $layer = $this->resolveLayer();

        $perimetersPath = $layer->path.'/src/Access/Perimeters';

        if (! is_dir($perimetersPath)) {
            $perimetersLayer = $this->resolvePerimetersLayer();

            $perimetersPath = $perimetersLayer->path.'/src/Access/Perimeters';
        }

        return new Collection(Finder::create()->files()->depth(0)->in($perimetersPath))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    protected function findAvailableModels()
    {
        $layer = $this->resolveLayer();

        $modelPath = $layer->path.'/src/Models';

        return new Collection(Finder::create()->files()->depth(0)->in($modelPath))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    protected function qualifyModel(string $model): string
    {
        $model = ltrim($model, '\\/');
        $model = str_replace('/', '\\', $model);
        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return $rootNamespace.'Models\\'.$model;
    }
}
