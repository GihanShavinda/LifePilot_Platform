<?php

namespace App\Domain\Documents\Enums;

enum ExtractionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Completed = 'completed';
    case Failed = 'failed';
}
