<?php

namespace Functional\Users\Filament\Resources\Users;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Users\Filament\Resources\Users\Pages\CreateUser;
use Functional\Users\Filament\Resources\Users\Pages\EditUser;
use Functional\Users\Filament\Resources\Users\Pages\ListUsers;
use Functional\Users\Filament\Resources\Users\Schemas\UserForm;
use Functional\Users\Filament\Resources\Users\Tables\UsersTable;
use Functional\Users\Models\User;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
