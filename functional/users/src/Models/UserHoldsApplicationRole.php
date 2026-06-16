<?php

namespace Functional\Users\Models;

use Functional\Applications\Models\ApplicationRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table('user_holds_application_roles')]
#[Fillable(['user_id', 'application_role_id', 'order'])]
class UserHoldsApplicationRole extends Pivot
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applicationRole(): BelongsTo
    {
        return $this->belongsTo(ApplicationRole::class);
    }
}
