<?php

namespace Functional\Users\Models;

use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Functional\Applications\Models\ApplicationRole;
use Functional\Organizations\Models\Site;
use Functional\Users\Database\Factories\UserFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Lomkit\Access\Controls\HasControl;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Technical\Filament\Models\PanelUser;

#[Fillable(['id', 'site_id', 'manager_id', 'email', 'firstname', 'lastname', 'language', 'password'])]
#[Hidden(['password'])]
#[UseFactory(UserFactory::class)]
class User extends PanelUser implements HasLocalePreference, HasMedia, SyncableAggregate
{
    use HasApiTokens;
    use HasControl;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use InteractsWithMedia;
    use Notifiable;
    use SoftDeletes;
    use SyncsToReplica;

    /** Pins Spatie permission lookups to the 'api' guard so Filament's web-guard switch does not break canAccessPanel(). */
    protected string $guard_name = 'api';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function directManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directManaged(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function applicationRoles(): BelongsToMany
    {
        return $this->belongsToMany(ApplicationRole::class, 'user_holds_application_roles')
            ->using(UserHoldsApplicationRole::class)
            ->withPivot('order')
            ->orderByPivot('order')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function preferredLocale(): string
    {
        return $this->language;
    }

    public function syncTenantScope(): ?string
    {
        return $this->site()->withTrashed()->first()?->syncTenantScope();
    }

    /**
     * @param  array<int, string>  $clientIds
     */
    public static function syncSnapshotQuery(array $clientIds): Builder
    {
        return static::query()
            ->whereHas('site', fn ($query) => $query->whereIn('client_id', $clientIds));
    }
}
