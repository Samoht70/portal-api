<?php

namespace Functional\Organizations\Models;

use Functional\Organizations\Database\Factories\ClientFactory;
use Functional\Subscriptions\Models\Subscription;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Controls\HasControl;

#[Fillable(['id', 'name'])]
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    use HasControl, HasFactory, HasUuids, SoftDeletes;

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, Site::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
