<?php

namespace Functional\Applications\Access\Controls;

use Functional\Applications\Models\RoleDefinition;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;
use Technical\AccessControl\Access\Perimeters\GlobalPerimeter;

class RoleDefinitionControl extends Control
{
    /**
     * The model the control refers to.
     * @var class-string<Model>
     */
    protected string $model = RoleDefinition::class;

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
                ->should(function (Model&User $user, RoleDefinition $roleDefinition) {
                    return true;
                })
                ->query(function (Builder $query, Model&User $user) {
                    return $query;
                }),
        ];
    }
}
