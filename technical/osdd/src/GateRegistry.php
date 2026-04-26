<?php

namespace Technical\Osdd;

use Illuminate\Support\Facades\Gate;

class GateRegistry
{
    public function push(array $gates): void
    {
        foreach ($gates as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
