<?php

namespace Technical\AccessControl\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DependentPolicy
{
    /**
     * The model class to which permission checks are delegated.
     */
    protected string $delegateTo {
        get {
            return $this->delegateTo;
        }
    }

    /**
     * Resolves the delegate model instance from the given child model.
     * Defaults to resolving the relationship using the camelCase class basename of $delegateTo.
     * Override this method if your relationship name differs from the convention.
     *
     * @param Model $model The child model instance.
     *
     * @return Model The parent model instance.
     */
    protected function resolveDelegate(Model $model): Model
    {
        $relation = $this->delegateTo
                |> class_basename(...)
                |> Str::camel(...);

        return $model->{$relation};
    }

    /**
     * Determine if the user is authorized to view any instances of the model.
     *
     * @param Model $user The user for which the permission check is performed.
     *
     * @return bool True if the user is authorized to view any instances, false otherwise.
     */
    public function viewAny(Model $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', $this->delegateTo);
    }

    /**
     * Checks whether a specific model instance is viewable by the given user.
     *
     * @param Model $user  The user whose permission to view the model is being evaluated.
     * @param Model $model The model instance for which view permission is checked.
     *
     * @return bool True if the user is authorized to view the model instance, false otherwise.
     */
    public function view(Model $user, Model $model): bool
    {
        return Gate::forUser($user)->allows('viewAny', $this->resolveDelegate($model));
    }

    /**
     * Checks if the given user has permission to create a new instance of the model.
     *
     * @param Model $user The user whose permission to create the model is being verified.
     *
     * @return bool True if the user is allowed to create a new model instance, false otherwise.
     */
    public function create(Model $user): bool
    {
        return Gate::forUser($user)->allows('create', $this->delegateTo);
    }

    /**
     * Determines whether the user is authorized to update the specified model instance.
     *
     * @param Model $user  The user attempting to perform the update.
     * @param Model $model The model instance targeted for update.
     *
     * @return bool True if the update action is permitted, false otherwise.
     */
    public function update(Model $user, Model $model): bool
    {
        return Gate::forUser($user)->allows('update', $this->resolveDelegate($model));
    }

    /**
     * Determines if the specified user is authorized to delete the given model instance.
     *
     * @param Model $user  The user attempting the deletion.
     * @param Model $model The model instance to be deleted.
     *
     * @return bool True if deletion is permitted, false otherwise.
     */
    public function delete(Model $user, Model $model): bool
    {
        return Gate::forUser($user)->allows('delete', $this->resolveDelegate($model));
    }

    /**
     * Determines if the specified user is authorized to restore the given model instance.
     *
     * @param Model $user  The user attempting the restoration.
     * @param Model $model The model instance to be restored.
     *
     * @return bool True if restoration is permitted, false otherwise.
     */
    public function restore(Model $user, Model $model): bool
    {
        return Gate::forUser($user)->allows('restore', $this->resolveDelegate($model));
    }

    /**
     * Determines if the specified user is authorized to force delete the given model instance.
     *
     * @param Model $user  The user attempting the force deletion.
     * @param Model $model The model instance to be force deleted.
     *
     * @return bool True if force deletion is permitted, false otherwise.
     */
    public function forceDelete(Model $user, Model $model): bool
    {
        return Gate::forUser($user)->allows('forceDelete', $this->resolveDelegate($model));
    }
}
