<?php

namespace Functional\Users\Models;

use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Functional\Applications\Models\ApplicationRole;
use Functional\Organizations\Models\Site;
use Functional\Users\Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Lomkit\Access\Controls\HasControl;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['id', 'site_id', 'manager_id', 'email', 'firstname', 'lastname', 'language'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements HasLocalePreference, HasMedia, MustVerifyEmail, SyncableAggregate
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
    use TwoFactorAuthenticatable;

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
