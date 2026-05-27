<?php

namespace Functional\Applications\Enums;

enum RoleDefinitionSlug: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Standard = 'standard';
    case Director = 'director';
    case SalesAssistant = 'sales-assistant';
    case Sales = 'sales';
    case FieldTechnician = 'field-technician';
    case Coordinator = 'coordinator';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
