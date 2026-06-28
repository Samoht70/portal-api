<?php

namespace Functional\Organizations\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->relationship('client', 'name')
                ->searchable()
                ->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('country')->required()->maxLength(255),
            TextInput::make('country_alpha')->required()->maxLength(2),
        ]);
    }
}
