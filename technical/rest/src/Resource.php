<?php

namespace Technical\Rest;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource as RestResource;

abstract class Resource extends RestResource
{
    /**
     * Build a "search" query for fetching resource.
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a query for mutating resource.
     */
    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "destroy" query for the given resource.
     */
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "restore" query for the given resource.
     */
    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "forceDelete" query for the given resource.
     */
    public function forceDeleteQuery(RestRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
