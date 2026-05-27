<?php

namespace Functional\Applications\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Functional\Applications\Database\Factories\PackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

#[UseFactory(PackFactory::class)]
#[Fillable(['slug'])]
class Pack extends Model implements TranslatableContract
{
    use HasControl;
    use HasFactory;
    use HasUuids;
    use Translatable;

    public $translatedAttributes = ['name'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
