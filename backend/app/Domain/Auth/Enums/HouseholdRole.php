<?php

namespace App\Domain\Auth\Enums;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    // Backward-compatible with P1-P9 records/tests. P10 normalizes this to Member behavior.
    case FamilyMember = 'family_member';
}
