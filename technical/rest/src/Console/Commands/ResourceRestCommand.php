<?php

namespace Technical\Rest\Console\Commands;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Commands\ResourceMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;
use Xefi\LaravelOSDD\Console\Commands\Make\ChoosesOsddLayer;

#[AsCommand(name: 'osdd:rest-resource')]
class ResourceRestCommand extends ResourceMakeCommand
{
    use ChoosesOsddLayer;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'osdd:rest-resource';

    public function handle(): ?bool
    {
        // Bypass ModelMakeCommand::handle()'s interactive "additional components" prompt
        // by calling GeneratorCommand::handle() directly via grandparent scope binding.
        $grandparentHandle = Closure::bind(
            fn () => parent::handle(),
            $this,
            ResourceMakeCommand::class
        );

        $result = $grandparentHandle();

        if ($result === false && ! $this->option('force')) {
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

        $relative = Str::replaceFirst($layerNamespace.'\\Rest\\Resources\\', '', $name);

        return $layer->path.'/src/Rest/Resources/'.str_replace('\\', '/', $relative).'.php';
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
