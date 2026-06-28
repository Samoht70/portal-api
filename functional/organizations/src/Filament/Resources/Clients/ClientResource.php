<?php

namespace Functional\Organizations\Filament\Resources\Clients;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Organizations\Filament\Resources\Clients\Pages\CreateClient;
use Functional\Organizations\Filament\Resources\Clients\Pages\EditClient;
use Functional\Organizations\Filament\Resources\Clients\Pages\ListClients;
use Functional\Organizations\Filament\Resources\Clients\RelationManagers\SitesRelationManager;
use Functional\Organizations\Filament\Resources\Clients\Schemas\ClientForm;
use Functional\Organizations\Filament\Resources\Clients\Tables\ClientsTable;
use Functional\Organizations\Models\Client;
use UnitEnum;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Organizations';

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
