<?php

namespace Technical\Translations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
#[Fillable(['name'])]
class PackTranslation extends Model
{
    use HasUuids;
}
