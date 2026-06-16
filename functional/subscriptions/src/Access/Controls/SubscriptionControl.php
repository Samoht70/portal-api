<?php

namespace Functional\Subscriptions\Access\Controls;

use Functional\Subscriptions\Models\Subscription;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\ClientPerimeter;
use Technical\AccessControl\Access\Perimeters\GlobalPerimeter;

class SubscriptionControl extends Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Subscription>
     */
    protected string $model = Subscription::class;

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        $table = (new $this->model)->getTable();
        $clientForeignKey = (new $this->model)->client()->getForeignKeyName();

        return [
            GlobalPerimeter::new()
                ->allowed(function (Model&User $user, string $method) use ($table) {
                    return $user->can(sprintf('%s global %s', $method, $table));
                })
                ->should(function (Model&User $user, Subscription $subscription) {
                    return true;
                })
                ->query(function (Builder $query, User $user) {
                    return $query;
                }),

            ClientPerimeter::new()
                ->allowed(function (Model&User $user, string $method) use ($table) {
                    return $user->can(sprintf('%s client %s', $method, $table));
                })
                ->should(function (Model&User $user, Subscription $model) {
                    return $model->client()->getParentKey() === $user->site->client()->getParentKey();
                })
                ->query(function (Builder $query, Model&User $user) use ($clientForeignKey) {
                    return $query->where($clientForeignKey, $user->site->client()->getParentKey());
                }),
        ];
    }
}
