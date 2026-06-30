<?php

namespace Functional\Subscriptions\Filament\Resources\Subscriptions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Functional\Subscriptions\Filament\Resources\Subscriptions\SubscriptionResource;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;
}
