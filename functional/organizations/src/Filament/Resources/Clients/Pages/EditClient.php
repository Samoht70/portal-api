<?php

namespace Functional\Organizations\Filament\Resources\Clients\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Functional\Organizations\Filament\Resources\Clients\ClientResource;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
