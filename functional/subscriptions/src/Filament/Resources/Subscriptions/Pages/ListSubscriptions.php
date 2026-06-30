<?php

namespace Functional\Subscriptions\Filament\Resources\Subscriptions\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Functional\Subscriptions\Filament\Resources\Subscriptions\SubscriptionResource;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
