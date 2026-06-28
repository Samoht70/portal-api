<?php

namespace Functional\Applications\Filament\Resources\Applications;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Functional\Applications\Filament\Resources\Applications\Pages\CreateApplication;
use Functional\Applications\Filament\Resources\Applications\Pages\EditApplication;
use Functional\Applications\Filament\Resources\Applications\Pages\ListApplications;
use Functional\Applications\Filament\Resources\Applications\RelationManagers\RolesRelationManager;
use Functional\Applications\Filament\Resources\Applications\Schemas\ApplicationForm;
use Functional\Applications\Filament\Resources\Applications\Tables\ApplicationsTable;
use Functional\Applications\Models\Application;

/** Filament resource for managing Applications. */
class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Applications';

    public static function form(Schema $schema): Schema
    {
        return ApplicationForm::configure($schema);
    }

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
            'create' => CreateApplication::route('/create'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }
}
