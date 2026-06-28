<?php

namespace Functional\Applications\Filament\Resources\Applications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Defines the columns and default sort for the Applications list table. */
class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"),
                    )),

                TextColumn::make('slug')
                    ->badge()
                    ->sortable(),

                TextColumn::make('pack.name')
                    ->label('Pack'),

                TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Roles'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
