<?php

namespace Functional\Organizations\Filament\Resources\Clients\Pages;

use Filament\Resources\Pages\CreateRecord;
use Functional\Organizations\Filament\Resources\Clients\ClientResource;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;
}
