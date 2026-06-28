<?php

namespace Functional\Applications\Filament\Resources\Packs;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Functional\Applications\Filament\Resources\Packs\Pages\ListPacks;
use Functional\Applications\Filament\Resources\Packs\Schemas\PackForm;
use Functional\Applications\Filament\Resources\Packs\Tables\PacksTable;
use Functional\Applications\Models\Pack;

/** Filament resource for managing Packs. */
class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Applications';

    public static function form(Schema $schema): Schema
    {
        return PackForm::configure($schema);
    }

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
