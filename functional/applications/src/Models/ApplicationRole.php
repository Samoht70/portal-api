<?php

namespace Functional\Applications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'role_definition_id', 'is_default'])]
class ApplicationRole extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(RoleDefinition::class, 'role_definition_id');
    }
}
