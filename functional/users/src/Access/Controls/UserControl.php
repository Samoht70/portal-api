<?php

namespace Functional\Users\Access\Controls;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\ClientPerimeter;
use Technical\AccessControl\Access\Perimeters\GlobalPerimeter;
use Technical\AccessControl\Access\Perimeters\OwnPerimeter;

class UserControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<User>
     */
    protected string $model = User::class;

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
                ->should(function (Model&User $user, User $model) {
                    return true;
                })
                ->query(function (Builder $query, User $user) {
                    return $query;
                }),

            ClientPerimeter::new()
                ->allowed(function (Model&User $user, string $method) {
                    return $user->can(sprintf('%s client %s', $method, (new $this->model)->getTable()));
                })
                ->should(function (Model&User $user, User $model) {
                    return $model
                        ->site()
                        ->where('client_id', $user->site->client_id)
                        ->exists();
                })
                ->query(function (Builder $query, User $user) {
                    return $query
                        ->whereHas('site.client', function (Builder $whereHas) use ($user) {
                            $whereHas->whereKey($user->site->client_id);
                        });
                }),

            OwnPerimeter::new()
                ->allowed(function (Model&User $user, string $method) {
                    return $user->can(sprintf('%s own %s', $method, (new $this->model)->getTable()));
                })
                ->should(function (Model&User $user, User $model) {
                    return $model->is($user);
                })
                ->query(function (Builder $query, User $user) {
                    return $query->whereKey($user);
                }),
        ];
    }
}
