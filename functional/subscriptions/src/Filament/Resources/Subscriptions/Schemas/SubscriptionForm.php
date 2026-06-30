<?php

namespace Functional\Subscriptions\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Functional\Applications\Models\Application;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->relationship('client', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('application_id')
                ->relationship('application', 'slug')
                ->getOptionLabelFromRecordUsing(fn (Application $record): string => $record->slug->value)
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('licenses')
                ->numeric()
                ->minValue(0)
                ->required(),
        ]);
    }
}
