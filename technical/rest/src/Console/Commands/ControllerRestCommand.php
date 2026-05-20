<?php

namespace Technical\Rest\Console\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lomkit\Rest\Console\Commands\ControllerMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;
use Xefi\LaravelOSDD\Console\Commands\Make\ChoosesOsddLayer;

#[AsCommand(name: 'osdd:rest-controller')]
class ControllerRestCommand extends ControllerMakeCommand
{
    use ChoosesOsddLayer;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'osdd:rest-controller';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        // Bypass ModelMakeCommand::handle()'s interactive "additional components" prompt
        // by calling GeneratorCommand::handle() directly via grandparent scope binding.
        $grandparentHandle = \Closure::bind(
            fn() => parent::handle(),
            $this,
            ControllerMakeCommand::class
        );

        $result = $grandparentHandle();

        if ($result === false) {
            return false;
        }

        return null;
    }

    protected function rootNamespace(): string
    {
        return $this->resolveLayer()->manifest->rootNamespace();
    }

    protected function getPath($name): string
    {
        $layer = $this->resolveLayer();
        $layerNamespace = rtrim($layer->manifest->rootNamespace(), '\\');

        $relative = Str::replaceFirst($layerNamespace . '\\Rest\\Controllers\\', '', $name);

        return $layer->path . '/src/Rest/Controllers/' . str_replace('\\', '/', $relative) . '.php';
    }

    protected function possibleResources(): array
    {
        $layer = $this->resolveLayer();

        $resourcePath = $layer->path . '/src/Rest/Resources';

        return (new Collection(Finder::create()->files()->depth(0)->in($resourcePath)))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    protected function parseResource($model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Resource name contains invalid characters.');
        }

        return $this->qualifyResource($model);
    }

    /**
     * Qualify the given resource class base name.
     *
     * @param string $resource
     *
     * @return string
     */
    protected function qualifyResource(string $resource): string
    {
        $resource = ltrim($resource, '\\/');

        $resource = str_replace('/', '\\', $resource);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($resource, $rootNamespace)) {
            return $resource;
        }

        return $rootNamespace.'Rest\\Resources\\'.$resource;
    }
}
