<?php

namespace Functional\Subscriptions\Models;

use Functional\Applications\Models\Application;
use Functional\Subscriptions\Database\Factories\ApplicationSyncEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ApplicationSyncEndpointFactory::class)]
#[Fillable(['application_id', 'endpoint_url', 'secret', 'sync_enabled'])]
#[Hidden(['secret'])]
class ApplicationSyncEndpoint extends Model
{
    use HasFactory;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'sync_enabled' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
