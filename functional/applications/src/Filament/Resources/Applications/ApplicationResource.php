<?php

namespace Functional\Applications\Filament\Resources\Applications;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Applications\Filament\Resources\Applications\Pages\ListApplications;
use Functional\Applications\Filament\Resources\Applications\RelationManagers\RolesRelationManager;
use Functional\Applications\Filament\Resources\Applications\Tables\ApplicationsTable;
use Functional\Applications\Models\Application;
use UnitEnum;

/** Filament resource for managing Applications. */
class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Applications';

    public static function table(Table $table): Table
    {
        return ApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
        ];
    }
}
