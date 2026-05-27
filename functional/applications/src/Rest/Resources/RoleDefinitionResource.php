<?php

namespace Functional\Applications\Rest\Resources;

use Functional\Applications\Models\RoleDefinition;
use Illuminate\Contracts\Database\Eloquent\Builder as ContractBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;

class RoleDefinitionResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = RoleDefinition::class;

    /**
     * The exposed fields that could be provided
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'slug',
        ];
    }

    /**
     * The exposed relations that could be provided
     */
    public function relations(RestRequest $request): array
    {
        return [];
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

    public function searchQuery(RestRequest $request, ContractBuilder $query): ContractBuilder
    {
        return $query->controlled();
    }
}
