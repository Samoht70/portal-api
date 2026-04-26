<?php

namespace Technical\XefiFaker\Database\Faker\Extensions;

use Illuminate\Support\Facades\File;
use Random\Randomizer;
use Symfony\Component\Finder\SplFileInfo;
use Xefi\Faker\Extensions\Extension;

class SampleBackgroundsExtension extends Extension
{
    private string $backgroundDirectory;

    private array $calculatedBackgroundsPaths;

    public function __construct(Randomizer $randomizer)
    {
        $this->backgroundDirectory = sprintf('%s/%s', __DIR__, '../SampleBackgrounds');

        parent::__construct($randomizer);
    }

    protected function getBackgroundPaths(): array
    {
        return $this->calculatedBackgroundsPaths ?? ($this->calculatedBackgroundsPaths = collect(File::allFiles($this->backgroundDirectory))
            ->pluck(fn (SplFileInfo $fileInfo) => $fileInfo->getRelativePathname())
            ->toArray());
    }

    public function copySampleBackgroundToPath(string $path): string
    {
        $image = $this->pickArrayRandomElement($this->getBackgroundPaths());

        copy($this->backgroundDirectory.'/'.$image, $path);

        return $path;
    }
}
