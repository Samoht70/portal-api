<?php

namespace Functional\Subscriptions\Models;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Database\Factories\SubscriptionFactory;
use Functional\Subscriptions\Events\SubscriptionGranted;
use Functional\Subscriptions\Events\SubscriptionRevoked;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

#[UseFactory(SubscriptionFactory::class)]
#[Fillable(['client_id', 'application_id', 'licenses'])]
class Subscription extends Model
{
    use HasControl;
    use HasFactory;
    use HasUuids;

    protected $dispatchesEvents = [
        'created' => SubscriptionGranted::class,
        'deleted' => SubscriptionRevoked::class,
    ];

    protected function casts(): array
    {
        return [
            'licenses' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
