<?php

namespace Functional\Organizations\Filament\Resources\Sites;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Organizations\Filament\Resources\Sites\Pages\CreateSite;
use Functional\Organizations\Filament\Resources\Sites\Pages\EditSite;
use Functional\Organizations\Filament\Resources\Sites\Pages\ListSites;
use Functional\Organizations\Filament\Resources\Sites\Schemas\SiteForm;
use Functional\Organizations\Filament\Resources\Sites\Tables\SitesTable;
use Functional\Organizations\Models\Site;
use UnitEnum;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Organizations';

    public static function form(Schema $schema): Schema
    {
        return SiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'create' => CreateSite::route('/create'),
            'edit' => EditSite::route('/{record}/edit'),
        ];
    }
}
