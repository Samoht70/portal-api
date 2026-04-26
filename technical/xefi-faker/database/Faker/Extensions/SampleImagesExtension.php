<?php

namespace Technical\XefiFaker\Database\Faker\Extensions;

use Illuminate\Support\Facades\File;
use Random\Randomizer;
use Symfony\Component\Finder\SplFileInfo;
use Xefi\Faker\Extensions\Extension;

class SampleImagesExtension extends Extension
{
    private string $imageDirectory;

    private array $calculatedImagesPaths;

    public function __construct(Randomizer $randomizer)
    {
        $this->imageDirectory = sprintf('%s/%s', __DIR__, '../SampleImages');

        parent::__construct($randomizer);
    }

    protected function getImagePaths(): array
    {
        return $this->calculatedImagesPaths ?? ($this->calculatedImagesPaths = collect(File::allFiles($this->imageDirectory))
            ->pluck(fn (SplFileInfo $fileInfo) => $fileInfo->getRelativePathname())
            ->toArray());
    }

    public function copySampleImageToPath(string $path): string
    {
        $image = $this->pickArrayRandomElement($this->getImagePaths());

        copy($this->imageDirectory.'/'.$image, $path);

        return $path;
    }
}
