<?php

namespace Functional\Subscriptions\Filament\Resources\Subscriptions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Pages\EditSubscription;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Schemas\SubscriptionForm;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Tables\SubscriptionsTable;
use Functional\Subscriptions\Models\Subscription;
use UnitEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return SubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
