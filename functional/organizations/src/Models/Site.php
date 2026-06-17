<?php

namespace Functional\Organizations\Models;

use Functional\Organizations\Database\Factories\SiteFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Controls\HasControl;
use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;

#[Fillable(['id', 'client_id', 'name', 'country', 'country_alpha'])]
#[UseFactory(SiteFactory::class)]
class Site extends Model implements SyncableAggregate
{
    use HasControl, HasFactory, HasUuids, SoftDeletes, SyncsToReplica;

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function syncTenantScope(): ?string
    {
        return $this->client()->getParentKey();
    }
}
