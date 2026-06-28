<?php

namespace Functional\Applications\Filament\Resources\Applications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Functional\Applications\Filament\Resources\Applications\ApplicationResource;

/** Creates a new Application record. */
class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;
}
