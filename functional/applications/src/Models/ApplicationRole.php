<?php

namespace Functional\Applications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['application_id', 'role_definition_id', 'is_default'])]
class ApplicationRole extends Pivot
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean'
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(RoleDefinition::class);
    }
}
