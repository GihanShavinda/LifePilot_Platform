<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentExtraction;

class DuplicateExtractionDetector
{
    public function find(
        int $documentVersionId,
        string $sourceTextHash,
        string $extractorVersion,
        ?string $modelVersion
    ): ?DocumentExtraction {
        return DocumentExtraction::query()
            ->where('document_version_id', $documentVersionId)
            ->where('source_text_hash', $sourceTextHash)
            ->where('extractor_version', $extractorVersion)
            ->where('model_version', $modelVersion)
            ->whereIn('status', ['completed', 'needs_review'])
            ->latest('id')
            ->first();
    }
}
