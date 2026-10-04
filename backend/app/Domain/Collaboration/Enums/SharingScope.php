<?php

namespace App\Domain\Collaboration\Enums;

enum SharingScope: string
{
    case Private = 'private';
    case Household = 'household';
}
