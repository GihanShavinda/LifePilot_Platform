<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentExtraction extends Model
{
    protected $fillable = [
        'document_id',
        'document_version_id',
        'version_number',
        'status',
        'source_text_hash',
        'source_text',
        'document_type',
        'overall_confidence',
        'extractor_version',
        'model_version',
        'validation_errors',
        'raw_payload',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExtractionStatus::class,
            'overall_confidence' => 'float',
            'validation_errors' => 'array',
            'raw_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ExtractedField::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ExtractionReview::class);
    }
}
