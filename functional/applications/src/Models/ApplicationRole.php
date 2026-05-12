<?php

namespace Functional\Applications\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Functional\Applications\Database\Factories\ApplicationRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ApplicationRoleFactory::class)]
#[Fillable(['application_id', 'slug'])]
class ApplicationRole extends Model implements TranslatableContract
{
    use HasFactory, HasUuids, Translatable;

    public $translatedAttributes = ['name'];

    protected function casts(): array
    {
        return [
            'slug' => ''
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
