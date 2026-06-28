<?php

namespace Functional\Applications\Filament\Resources\Packs\Pages;

use Filament\Resources\Pages\EditRecord;
use Functional\Applications\Filament\Resources\Packs\PackResource;

/** Edits an existing Pack record. */
class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
