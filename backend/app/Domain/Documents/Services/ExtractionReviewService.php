<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\FieldReviewStatus;
use App\Domain\Documents\Enums\ReviewDecision;
use App\Domain\Documents\Models\ExtractedField;
use App\Domain\Documents\Models\ExtractionReview;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

class ExtractionReviewService
{
    public function review(
        User $user,
        ExtractedField $field,
        ReviewDecision $decision,
        mixed $editedValue = null,
        ?string $note = null
    ): ExtractedField {
        return DB::transaction(function () use ($user, $field, $decision, $editedValue, $note) {
            $previous = $field->value;
            $reviewedValue = null;

            if ($decision === ReviewDecision::Edit) {
                $reviewedValue = $editedValue;
                $field->value = $editedValue;
                $field->normalized_value = $editedValue;
                $field->review_status = FieldReviewStatus::Edited;
            } elseif ($decision === ReviewDecision::Accept) {
                $reviewedValue = $field->value;
                $field->review_status = FieldReviewStatus::Accepted;
            } else {
                $field->review_status = FieldReviewStatus::Rejected;
            }

            $field->save();

            ExtractionReview::create([
                'document_extraction_id' => $field->document_extraction_id,
                'extracted_field_id' => $field->id,
                'reviewed_by' => $user->id,
                'decision' => $decision,
                'previous_value' => $previous,
                'reviewed_value' => $reviewedValue,
                'note' => $note,
            ]);

            return $field->fresh(['evidence', 'reviews']);
        });
    }
}
