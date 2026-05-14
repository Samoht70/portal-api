<?php

namespace Functional\Applications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['application_id', 'role_definition_id'])]
class ApplicationRole extends Pivot
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(RoleDefinition::class);
    }
}
