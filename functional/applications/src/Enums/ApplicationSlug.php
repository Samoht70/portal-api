<?php

namespace Functional\Applications\Enums;

enum ApplicationSlug: string
{
    case BUSINESS_CARD = 'business-card';
    case LEAVE_MANAGEMENT = 'leave-management';
    case ATTENDANCE_TRACKING = 'attendance-tracking';
    case EMPLOYEE_GUIDE = 'employee-guide';
    case EXPENSE_REPORTS = 'expense-reports';
    case SURVEY = 'survey';
    case CARPOOLING = 'carpooling';
    case WORK_ORDER = 'work-order';
    case SALESPERSON = 'salesperson';
    case SALES_UP = 'sales-up';
}
