<?php

namespace Functional\Organizations\Models;

use Functional\Applications\Models\Application;
use Functional\Organizations\Database\Factories\ClientFactory;
use Functional\Subscriptions\Models\Subscription;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Controls\HasControl;
use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;

#[Fillable(['id', 'name'])]
#[UseFactory(ClientFactory::class)]
class Client extends Model implements SyncableAggregate
{
    use HasControl, HasFactory, HasUuids, SoftDeletes, SyncsToReplica;

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

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'subscriptions')
            ->withPivot('licenses')
            ->withTimestamps();
    }

    public function syncTenantScope(): ?string
    {
        return $this->getKey();
    }
}
