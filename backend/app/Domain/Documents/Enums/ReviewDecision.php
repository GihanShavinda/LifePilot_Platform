<?php

namespace App\Domain\Documents\Enums;

enum ReviewDecision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case Edit = 'edit';
}
