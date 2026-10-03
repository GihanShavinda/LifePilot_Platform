<?php

namespace App\Domain\Actions\Enums;

enum ActionPlanStatus: string
{
    case Preview = 'preview';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
