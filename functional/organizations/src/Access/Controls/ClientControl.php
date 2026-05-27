<?php

namespace Functional\Organizations\Access\Controls;

use Functional\Organizations\Models\Client;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\GlobalPerimeter;
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
            GlobalPerimeter::new()
                ->allowed(function (Model&User $user, string $method) {
                    return $user->can(sprintf('%s global %s', $method, (new $this->model)->getTable()));
                })
                ->should(function (Model&User $user, Client $client) {
                    return true;
                })
                ->query(function (Builder $query, Model&User $user) {
                    return $query;
                }),

            OwnPerimeter::new()
                ->allowed(function (Model&User $user, string $method) {
                    return $user->can(sprintf('%s own %s', $method, (new $this->model)->getTable()));
                })
                ->should(function (Model&User $user, Client $client) {
                    return $client
                        ->users()
                        ->whereKey($user)
                        ->exists();
                })
                ->query(function (Builder $query, Model&User $user) {
                    return $query
                        ->whereKey($user->site->client_id);
                }),
        ];
    }
}
