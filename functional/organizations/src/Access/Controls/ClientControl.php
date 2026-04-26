<?php

namespace Functional\Organizations\Access\Controls;

use Functional\Organizations\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\OwnPerimeter;

class ClientControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Client>
     */
    protected string $model = Client::class;

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        return [
            OwnPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s own clients', $method));
                })
                ->should(function (Model $user, Client $model) {
                    return $model->users()->whereKey($user)->exists();
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->whereKey($user->site->client_id);
                }),
        ];
    }
}
