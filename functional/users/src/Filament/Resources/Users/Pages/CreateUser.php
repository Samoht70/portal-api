<?php

namespace Functional\Users\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Functional\Users\Filament\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
