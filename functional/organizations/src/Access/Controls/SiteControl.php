<?php

namespace Functional\Organizations\Access\Controls;

use Functional\Organizations\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\ClientPerimeter;
use Technical\AccessControl\Access\Perimeters\OwnPerimeter;

class SiteControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Site>
     */
    protected string $model = Site::class;

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s client sites', $method));
                })
                ->should(function (Model $user, Site $model) {
                    return $model->client_id === $user->site->client_id;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->where('client_id', $user->site->client_id);
                }),

            OwnPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s own sites', $method));
                })
                ->should(function (Model $user, Site $model) {
                    return $model->getKey() === $user->site_id;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->whereKey($user->site_id);
                }),
        ];
    }
}
