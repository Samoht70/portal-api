<?php

namespace Functional\Applications\Models;

use Functional\Applications\Enums\RoleDefinitionSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Controls\HasControl;

#[Fillable(['slug'])]
class RoleDefinition extends Model
{
    use HasControl;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'slug' => RoleDefinitionSlug::class,
        ];
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_roles')
            ->using(ApplicationRole::class)
            ->withPivot(['is_default'])
            ->withTimestamps();
    }
}
