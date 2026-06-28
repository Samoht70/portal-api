<?php

namespace Functional\Applications\Filament\Resources\Packs\Pages;

use Filament\Resources\Pages\ListRecords;
use Functional\Applications\Filament\Resources\Packs\PackResource;

/** Lists all Packs in the admin panel. */
class ListPacks extends ListRecords
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
