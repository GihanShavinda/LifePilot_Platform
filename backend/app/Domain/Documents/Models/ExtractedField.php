<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\FieldReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractedField extends Model
{
    protected $fillable = [
        'document_extraction_id',
        'field_name',
        'value',
        'normalized_value',
        'confidence',
        'page',
        'evidence_text',
        'extractor_version',
        'model_version',
        'review_status',
        'source',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'normalized_value' => 'array',
            'confidence' => 'float',
            'page' => 'integer',
            'review_status' => FieldReviewStatus::class,
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class, 'document_extraction_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ExtractionEvidence::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ExtractionReview::class);
    }
}
