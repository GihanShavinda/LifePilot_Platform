<?php

namespace App\Domain\Actions\Enums;

enum ActionStepStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
}
