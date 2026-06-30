<?php

namespace Functional\Subscriptions\Filament\Resources\Subscriptions\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Functional\Subscriptions\Filament\Resources\Subscriptions\SubscriptionResource;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
