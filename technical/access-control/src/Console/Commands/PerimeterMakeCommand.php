<?php

namespace Technical\AccessControl\Console\Commands;

use Illuminate\Support\Str;
use Lomkit\Access\Console\PerimeterMakeCommand as BasePerimeterMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Xefi\LaravelOSDD\Console\Commands\Make\ChoosesOsddLayer;

#[AsCommand(name: 'osdd:perimeter')]
class PerimeterMakeCommand extends BasePerimeterMakeCommand
{
    use ChoosesOsddLayer;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'osdd:perimeter';

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        // Bypass PerimeterMakeCommand::handle()'s interactive "additional components" prompt
        // by calling GeneratorCommand::handle() directly via grandparent scope binding.
        $grandparentHandle = \Closure::bind(
            fn () => parent::handle(),
            $this,
            BasePerimeterMakeCommand::class
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

    protected function getDefaultNamespace($rootNamespace): string
    {
        return rtrim($rootNamespace, '\\').'\\Access\\Perimeters';
    }

    protected function getPath($name): string
    {
        $layer = $this->resolveLayer();
        $layerNamespace = rtrim($layer->manifest->rootNamespace(), '\\');

        $relative = Str::replaceFirst($layerNamespace.'\\Access\\Perimeters\\', '', $name);

        return $layer->path.'/src/Access/Perimeters/'.str_replace('\\', '/', $relative).'.php';
    }
}
