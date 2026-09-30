<?php

namespace App\Domain\Documents\Enums;

enum FieldReviewStatus: string
{
    case Pending = 'pending';
    case NeedsReview = 'needs_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Edited = 'edited';
}
