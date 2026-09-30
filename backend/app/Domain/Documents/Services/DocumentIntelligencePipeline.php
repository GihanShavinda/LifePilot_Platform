<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Contracts\StructuredExtractionClient;
use App\Domain\Documents\Enums\ExtractionStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Models\ExtractedField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentIntelligencePipeline
{
    public const EXTRACTOR_VERSION = 'lifepilot-p3.1';

    public function __construct(
        private NativeTextExtractionService $nativeText,
        private TesseractOcrService $ocr,
        private DeterministicFieldParser $deterministic,
        private StructuredExtractionClient $llm,
        private ExtractionSchema $schema,
        private ExtractionJsonValidator $jsonValidator,
        private EvidenceGroundingValidator $grounding,
        private PromptInjectionGuard $injectionGuard,
        private ConfidenceService $confidence,
        private DuplicateExtractionDetector $duplicates,
        private ExtractionConflictDetector $conflicts,
    ) {
    }

    public function process(
        Document $document,
        DocumentVersion $version,
        bool $force = false
    ): DocumentExtraction {
        $native = $this->nativeText->extract($version);
        $text = trim($native['text']);
        $pages = $native['pages'];
        $method = $native['method'];

        if ($this->requiresOcr($text)) {
            $ocr = $this->ocr->extract($version);

            if (trim($ocr['text']) !== '') {
                $text = trim($ocr['text']);
                $pages = $ocr['pages'];
                $method = $ocr['method'];
            }
        }

        $hash = hash('sha256', $text);
        $configuredModel = trim((string) config('documents.intelligence.llm.model_version', '')) ?: null;

        if (!$force) {
            $existing = $this->duplicates->find(
                $version->id,
                $hash,
                self::EXTRACTOR_VERSION,
                $configuredModel
            );

            if ($existing) {
                return $existing->load(['fields.evidence', 'reviews']);
            }
        }

        $extraction = $this->newExtraction($document, $version, $text, $hash, $configuredModel);

        try {
            $promptSignals = $this->injectionGuard->detect($text);
            $deterministicFields = $this->deterministic->parse($text, $pages);

            $llmResponse = $this->llm->extract($text, $this->schema->schema());
            $llmPayload = [
                'fields' => $llmResponse['fields'] ?? [],
            ];

            $validationErrors = $this->jsonValidator->validate($llmPayload);

            $llmFields = $validationErrors === []
                ? ($llmResponse['fields'] ?? [])
                : [];

            foreach ($llmFields as &$field) {
                $field['source'] = 'llm';
            }
            unset($field);

            $grounding = $this->grounding->filterGrounded(
                $text,
                array_merge($deterministicFields, $llmFields)
            );

            $accepted = $this->deduplicateCandidates($grounding['accepted']);
            $conflicts = $this->conflicts->conflictingFieldNames($accepted);
            $scores = [];

            DB::transaction(function () use (
                $extraction,
                $accepted,
                $conflicts,
                &$scores,
                $llmResponse
            ) {
                foreach ($accepted as $candidate) {
                    $confidence = round((float) ($candidate['confidence'] ?? 0), 4);
                    $scores[] = $confidence;
                    $fieldName = (string) $candidate['field_name'];
                    $reviewStatus = $this->confidence->reviewStatus(
                        $confidence,
                        in_array($fieldName, $conflicts, true)
                    );

                    $fingerprint = hash(
                        'sha256',
                        implode('|', [
                            $fieldName,
                            json_encode($candidate['normalized_value'] ?? $candidate['value']),
                            (string) ($candidate['page'] ?? ''),
                            trim((string) $candidate['evidence_text']),
                        ])
                    );

                    $field = ExtractedField::create([
                        'document_extraction_id' => $extraction->id,
                        'field_name' => $fieldName,
                        'value' => $candidate['value'],
                        'normalized_value' => $candidate['normalized_value'] ?? null,
                        'confidence' => $confidence,
                        'page' => $candidate['page'] ?? null,
                        'evidence_text' => trim((string) $candidate['evidence_text']),
                        'extractor_version' => self::EXTRACTOR_VERSION,
                        'model_version' => $candidate['source'] === 'llm'
                            ? ($llmResponse['model_version'] ?? null)
                            : null,
                        'review_status' => $reviewStatus,
                        'source' => $candidate['source'] ?? 'deterministic',
                        'fingerprint' => $fingerprint,
                    ]);

                    $field->evidence()->create([
                        'page' => $candidate['page'] ?? null,
                        'evidence_text' => trim((string) $candidate['evidence_text']),
                        'source' => $candidate['source'] ?? 'document',
                    ]);
                }
            });

            $overall = $this->confidence->overall($scores);
            $needsReview = $extraction->fields()
                ->whereIn('review_status', ['pending', 'needs_review'])
                ->exists();

            $documentTypeField = $extraction->fields()
                ->where('field_name', 'document_type')
                ->orderByDesc('confidence')
                ->first();

            $documentType = $documentTypeField?->normalized_value
                ?? $documentTypeField?->value;

            if (is_array($documentType)) {
                $documentType = reset($documentType);
            }

            $modelVersion = $llmResponse['model_version'] ?? $configuredModel;

            $extraction->update([
                'status' => $needsReview
                    ? ExtractionStatus::NeedsReview
                    : ExtractionStatus::Completed,
                'document_type' => is_scalar($documentType) ? (string) $documentType : null,
                'overall_confidence' => $overall,
                'model_version' => $modelVersion,
                'validation_errors' => $validationErrors,
                'raw_payload' => [
                    'text_method' => $method,
                    'prompt_injection_signals' => $promptSignals,
                    'rejected_ungrounded_fields' => $grounding['rejected'],
                    'llm' => $llmResponse['raw'] ?? null,
                ],
                'finished_at' => now(),
            ]);

            return $extraction->fresh(['fields.evidence', 'reviews']);
        } catch (\Throwable $e) {
            $extraction->update([
                'status' => ExtractionStatus::Failed,
                'validation_errors' => [$e->getMessage()],
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function requiresOcr(string $text): bool
    {
        $minimum = (int) config('documents.intelligence.native_text_min_chars', 80);

        if (mb_strlen(trim($text)) < $minimum) {
            return true;
        }

        $printable = preg_replace('/[^\pL\pN\pP\pS\s]/u', '', $text) ?? '';
        $ratio = mb_strlen($text) > 0
            ? mb_strlen($printable) / mb_strlen($text)
            : 0;

        return $ratio < 0.70;
    }

    private function newExtraction(
        Document $document,
        DocumentVersion $version,
        string $text,
        string $hash,
        ?string $modelVersion
    ): DocumentExtraction {
        $nextVersion = ((int) $document->extractions()->max('version_number')) + 1;

        return DocumentExtraction::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'version_number' => $nextVersion,
            'status' => ExtractionStatus::Processing,
            'source_text_hash' => $hash,
            'source_text' => $text,
            'extractor_version' => self::EXTRACTOR_VERSION,
            'model_version' => $modelVersion,
            'started_at' => now(),
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @return array<int,array<string,mixed>>
     */
    private function deduplicateCandidates(array $fields): array
    {
        $best = [];

        foreach ($fields as $field) {
            $key = implode('|', [
                (string) ($field['field_name'] ?? ''),
                mb_strtolower(trim((string) json_encode(
                    $field['normalized_value'] ?? $field['value'] ?? null
                ))),
                mb_strtolower(trim((string) ($field['evidence_text'] ?? ''))),
            ]);

            if (
                !isset($best[$key])
                || (float) ($field['confidence'] ?? 0) > (float) ($best[$key]['confidence'] ?? 0)
            ) {
                $best[$key] = $field;
            }
        }

        return array_values($best);
    }


}
