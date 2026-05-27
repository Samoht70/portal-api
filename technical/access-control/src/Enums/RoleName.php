<?php

namespace Technical\AccessControl\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Standard = 'standard';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
