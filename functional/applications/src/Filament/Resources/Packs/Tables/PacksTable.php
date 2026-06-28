<?php

namespace Functional\Applications\Filament\Resources\Packs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Defines the columns and default sort for the Packs list table. */
class PacksTable
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

                TextColumn::make('applications_count')
                    ->counts('applications')
                    ->label('Applications'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
