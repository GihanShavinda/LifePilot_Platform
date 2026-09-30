<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\ReviewDecision;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionReview extends Model
{
    protected $fillable = [
        'document_extraction_id',
        'extracted_field_id',
        'reviewed_by',
        'decision',
        'previous_value',
        'reviewed_value',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'previous_value' => 'array',
            'reviewed_value' => 'array',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class, 'document_extraction_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtractedField::class, 'extracted_field_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
