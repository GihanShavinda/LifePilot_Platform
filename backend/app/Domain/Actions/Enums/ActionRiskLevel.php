<?php

namespace App\Domain\Actions\Enums;

enum ActionRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
