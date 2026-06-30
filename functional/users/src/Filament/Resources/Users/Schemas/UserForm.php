<?php

namespace Functional\Users\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('site_id')
                ->relationship('site', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('manager_id')
                ->relationship('directManager', 'lastname')
                ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->lastname} {$record->firstname}")
                ->searchable(['lastname', 'firstname'])
                ->preload(),

            TextInput::make('firstname')
                ->required()
                ->maxLength(255),

            TextInput::make('lastname')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Select::make('language')
                ->options([
                    'fr' => 'Français',
                    'en' => 'English',
                ])
                ->required(),

            TextInput::make('password')
                ->password()
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
        ]);
    }
}
