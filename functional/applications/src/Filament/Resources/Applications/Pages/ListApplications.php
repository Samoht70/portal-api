<?php

namespace Functional\Applications\Filament\Resources\Applications\Pages;

use Filament\Resources\Pages\ListRecords;
use Functional\Applications\Filament\Resources\Applications\ApplicationResource;

/** Lists all Applications in the admin panel. */
class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
