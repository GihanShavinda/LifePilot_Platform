<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\FieldReviewStatus;

class ConfidenceService
{
    public function reviewStatus(float $confidence, bool $contradictory = false): FieldReviewStatus
    {
        if ($contradictory) {
            return FieldReviewStatus::NeedsReview;
        }

        $autoAccept = (float) config('documents.intelligence.auto_accept_confidence', 0.95);
        $reviewThreshold = (float) config('documents.intelligence.review_confidence', 0.65);

        if ($confidence >= $autoAccept) {
            return FieldReviewStatus::Pending;
        }

        if ($confidence >= $reviewThreshold) {
            return FieldReviewStatus::NeedsReview;
        }

        return FieldReviewStatus::NeedsReview;
    }

    /** @param array<int,float|int> $scores */
    public function overall(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 4);
    }
}
