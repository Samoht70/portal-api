<?php

namespace src;

use Lomkit\Access\Access;

class ControlRegistry
{
    public function push(array $controls): void
    {
        app()->make(Access::class)->addControls($controls);
    }
}
