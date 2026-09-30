<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionEvidence extends Model
{
    protected $fillable = [
        'extracted_field_id',
        'page',
        'evidence_text',
        'char_start',
        'char_end',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'char_start' => 'integer',
            'char_end' => 'integer',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtractedField::class, 'extracted_field_id');
    }
}
