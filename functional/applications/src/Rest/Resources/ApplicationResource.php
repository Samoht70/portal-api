<?php

namespace Functional\Applications\Rest\Resources;

use Functional\Applications\Models\Application;
use Illuminate\Contracts\Database\Eloquent\Builder as ContractBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Technical\Translations\Rest\Resources\ApplicationTranslationResource;

class ApplicationResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model = Application::class;

    /**
     * The exposed fields that could be provided
     * @param RestRequest $request
     * @return array
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'pack_id', 'slug',
        ];
    }

    /**
     * The exposed relations that could be provided
     * @param RestRequest $request
     * @return array
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('pack', PackResource::class),

            BelongsToMany::make('roles', RoleDefinitionResource::class)
                ->withPivotFields(['is_default']),

            HasMany::make('translations', ApplicationTranslationResource::class),
        ];
    }

    /**
     * The exposed scopes that could be provided
     * @param RestRequest $request
     * @return array
     */
    public function scopes(RestRequest $request): array
    {
        return [];
    }

    /**
     * The exposed limits that could be provided
     * @param RestRequest $request
     * @return array
     */
    public function limits(RestRequest $request): array
    {
        return [
            10,
            25,
            50
        ];
    }

    /**
     * The actions that should be linked
     * @param RestRequest $request
     * @return array
     */
    public function actions(RestRequest $request): array {
        return [];
    }

    /**
     * The instructions that should be linked
     * @param RestRequest $request
     * @return array
     */
    public function instructions(RestRequest $request): array {
        return [];
    }

    public function searchQuery(RestRequest $request, ContractBuilder $query): ContractBuilder
    {
        return $query
            ->withTranslation()
            ->controlled();
    }
}
