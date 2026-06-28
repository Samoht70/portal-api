<?php

namespace Functional\Organizations\Filament\Resources\Clients\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Functional\Organizations\Filament\Resources\Clients\ClientResource;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
