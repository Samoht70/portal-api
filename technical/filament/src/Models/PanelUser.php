<?php

namespace Technical\Filament\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Base authenticatable that fulfils the Filament panel contracts so app user models only extend it. */
abstract class PanelUser extends Authenticatable implements FilamentUser, HasName
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('access admin panel');
    }

    public function getFilamentName(): string
    {
        return trim("{$this->firstname} {$this->lastname}") ?: $this->email;
    }
}
