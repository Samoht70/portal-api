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
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ApplicationFactory::class)]
#[Fillable(['pack_id', 'slug'])]
class Application extends Model implements TranslatableContract
{
    use HasFactory, HasUuids, Translatable;

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

    public function roles(): HasMany
    {
        return $this->hasMany(ApplicationRole::class);
    }
}
