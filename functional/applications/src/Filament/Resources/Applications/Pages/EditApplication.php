<?php

namespace Functional\Applications\Filament\Resources\Applications\Pages;

use Filament\Resources\Pages\EditRecord;
use Functional\Applications\Filament\Resources\Applications\ApplicationResource;

/** Edits an existing Application record. */
class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
