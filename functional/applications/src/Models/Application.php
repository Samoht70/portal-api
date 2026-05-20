<?php

namespace Functional\Applications\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Functional\Applications\Database\Factories\ApplicationFactory;
use Functional\Applications\Enums\ApplicationSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Controls\HasControl;

#[UseFactory(ApplicationFactory::class)]
#[Fillable(['pack_id', 'slug'])]
class Application extends Model implements TranslatableContract
{
    use HasControl;
    use HasFactory;
    use HasUuids;
    use Translatable;

    public $translatedAttributes = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'slug' => ApplicationSlug::class,
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleDefinition::class, 'application_roles')
            ->using(ApplicationRole::class)
            ->withPivot(['is_default'])
            ->withTimestamps();
    }
}
