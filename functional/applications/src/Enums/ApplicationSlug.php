<?php

namespace Functional\Applications\Enums;

use Functional\Applications\Models\Application;

enum ApplicationSlug: string
{
    case BusinessCard = 'business-card';
    case LeaveManagement = 'leave-management';
    case Attendance = 'attendance';
    case EmployeeGuide = 'employee-guide';
    case ExpenseReports = 'expense-reports';
    case Survey = 'survey';
    case Carpooling = 'carpooling';
    case WorkOrder = 'work-order';
    case SalesPerson = 'salesperson';
    case SalesUp = 'sales-up';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function toModel(): Application
    {
        return Application::query()->where('slug', $this->value)->firstOrFail();
    }

    public function roles(): array
    {
        return match ($this) {
            self::Attendance, self::Survey, self::Carpooling => [
                RoleDefinitionSlug::Admin,
                RoleDefinitionSlug::Standard
            ],

            self::BusinessCard, self::LeaveManagement, self::EmployeeGuide, self::ExpenseReports => [
                RoleDefinitionSlug::Admin,
                RoleDefinitionSlug::Director,
                RoleDefinitionSlug::Standard,
            ],

            self::WorkOrder => [
                RoleDefinitionSlug::Admin,
                RoleDefinitionSlug::Coordinator,
                RoleDefinitionSlug::FieldTechnician,
            ],

            self::SalesPerson, self::SalesUp => [
                RoleDefinitionSlug::Admin,
                RoleDefinitionSlug::Manager,
                RoleDefinitionSlug::Sales,
                RoleDefinitionSlug::SalesAssistant,
            ],

            default => [],
        };
    }

    public function defaultRole(): RoleDefinitionSlug
    {
        return match ($this) {
            self::Attendance,
            self::Survey,
            self::Carpooling,
            self::BusinessCard,
            self::LeaveManagement,
            self::EmployeeGuide,
            self::ExpenseReports => RoleDefinitionSlug::Standard,

            self::WorkOrder => RoleDefinitionSlug::FieldTechnician,

            self::SalesPerson, self::SalesUp => RoleDefinitionSlug::Sales,
        };
    }
}
