<?php

namespace Technical\MediaLibrary\Rest\Resources;

use Lomkit\Rest\Http\Requests\RestRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Technical\Rest\Resource;

class MediaResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<User>
     */
    public static $model = Media::class;

    /**
     * The exposed fields that could be provided
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id',
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
}
