<?php
namespace App\Domain\Obligations\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Overdue = 'overdue';
}
