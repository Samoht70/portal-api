<?php

namespace Functional\Organizations\Rest\Resources;

use Functional\Applications\Rest\Resources\ApplicationResource;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Rest\Resources\SubscriptionResource;
use Functional\Users\Rest\Resources\UserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Technical\Rest\Resource;

class ClientResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Client>
     */
    public static $model = Client::class;

    /**
     * The exposed fields that could be provided
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'name',
        ];
    }

    /**
     * The exposed relations that could be provided
     */
    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('sites', SiteResource::class),

            HasMany::make('subscriptions', SubscriptionResource::class),

            HasManyThrough::make('users', UserResource::class),

            BelongsToMany::make('applications', ApplicationResource::class)
                ->withPivotFields(['licenses']),
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
