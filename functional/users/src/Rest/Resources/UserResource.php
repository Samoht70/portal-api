<?php

namespace Functional\Users\Rest\Resources;

use Functional\Applications\Rest\Resources\ApplicationRoleResource;
use Functional\Organizations\Rest\Resources\SiteResource;
use Functional\Users\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphToMany;
use Technical\AccessControl\Rest\Resources\RoleResource;
use Technical\MediaLibrary\Rest\Resources\MediaResource;
use Technical\Rest\Resource;

class UserResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<User>
     */
    public static $model = User::class;

    /**
     * The exposed fields that could be provided
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'site_id', 'manager_id', 'email', 'firstname', 'lastname', 'language',
        ];
    }

    /**
     * The exposed relations that could be provided
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('site', SiteResource::class)
                ->requiredOnCreation(),

            BelongsTo::make('directManager', UserResource::class),

            HasMany::make('directManaged', UserResource::class),

            BelongsToMany::make('applicationRoles', ApplicationRoleResource::class)
                ->withPivotFields(['order']),

            MorphMany::make('media', MediaResource::class),

            MorphToMany::make('roles', RoleResource::class)
                ->requiredOnCreation(),
        ];
    }

    /**
     * The exposed scopes that could be provided
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided
     */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50,
        ];
    }

    /**
     * The actions that should be linked
     */
    public function actions(RestRequest $request): array
    {
        return [];
    }

    /**
     * The instructions that should be linked
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }
}
