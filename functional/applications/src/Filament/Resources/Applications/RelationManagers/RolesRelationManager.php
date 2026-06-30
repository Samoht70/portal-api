<?php

namespace Functional\Applications\Filament\Resources\Applications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Manages roles related to an Application. */
class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('definition.slug')
                    ->label('Role definition'),

                IconColumn::make('is_default')
                    ->boolean(),
            ]);
    }
}
