<?php

namespace Functional\Applications\Filament\Resources\Packs;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Applications\Filament\Resources\Packs\Pages\ListPacks;
use Functional\Applications\Filament\Resources\Packs\Tables\PacksTable;
use Functional\Applications\Models\Pack;
use UnitEnum;

/** Filament resource for managing Packs. */
class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Applications';

    public static function table(Table $table): Table
    {
        return PacksTable::configure($table);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListPacks::route('/'),
        ];
    }
}
