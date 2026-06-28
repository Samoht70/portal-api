<?php

namespace Functional\Applications\Filament\Resources\Packs\Pages;

use Filament\Resources\Pages\CreateRecord;
use Functional\Applications\Filament\Resources\Packs\PackResource;

/** Creates a new Pack record. */
class CreatePack extends CreateRecord
{
    protected static string $resource = PackResource::class;
}
