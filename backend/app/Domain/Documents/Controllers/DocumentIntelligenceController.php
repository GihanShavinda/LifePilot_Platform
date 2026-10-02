<?php

namespace App\Domain\Documents\Controllers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Documents\Enums\ReviewDecision;
use App\Domain\Documents\Jobs\ProcessDocumentJob;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentProcessingJob;
use App\Domain\Documents\Models\ExtractedField;
use App\Domain\Documents\Requests\ReviewExtractedFieldRequest;
use App\Domain\Documents\Services\DocumentAuthorization;
use App\Domain\Documents\Services\ExtractionReviewService;
use App\Domain\Graph\Jobs\IndexDocumentForSemanticSearch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentIntelligenceController
{
    public function __construct(
        private DocumentAuthorization $authz,
        private ExtractionReviewService $reviews,
        private AuditService $audit,
    ) {
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::withTrashed()->findOrFail($id);
        $this->authz->ensure($request->user(), $document);

        $extraction = $document->extractions()
            ->with([
                'fields' => fn ($query) => $query
                    ->with(['evidence', 'reviews'])
                    ->orderBy('field_name')
                    ->orderByDesc('confidence'),
                'reviews',
            ])
            ->latest('version_number')
            ->first();

        return ApiResponse::success([
            'extraction' => $extraction
                ? $this->serializeExtraction($extraction)
                : null,
        ]);
    }

    public function versions(Request $request, int $id): JsonResponse
    {
        $document = Document::withTrashed()->findOrFail($id);
        $this->authz->ensure($request->user(), $document);

        $items = $document->extractions()
            ->latest('version_number')
            ->get()
            ->map(fn ($extraction) => [
                'id' => $extraction->id,
                'version_number' => $extraction->version_number,
                'status' => $this->enumValue($extraction->status),
                'document_type' => $extraction->document_type,
                'overall_confidence' => $extraction->overall_confidence,
                'extractor_version' => $extraction->extractor_version,
                'model_version' => $extraction->model_version,
                'created_at' => $extraction->created_at?->toIso8601String(),
            ])
            ->values();

        return ApiResponse::success(['versions' => $items]);
    }

    public function reprocess(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authz->ensure($request->user(), $document, true);

        $version = $document->versions()->latest('version_number')->firstOrFail();

        $job = DocumentProcessingJob::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'job_type' => 'intelligence_reprocess',
            'status' => 'queued',
            'result' => [
                'force_reprocess' => true,
            ],
        ]);

        $document->update(['processing_status' => 'queued']);

        ProcessDocumentJob::dispatch($job->id);

        $this->audit->record(
            'document.intelligence.reprocess_requested',
            $request->user(),
            $document,
            ['processing_job_id' => $job->id]
        );

        return ApiResponse::success([
            'message' => 'Document intelligence reprocessing queued.',
            'processing_job_id' => $job->id,
        ], 202);
    }

    public function reviewField(
        ReviewExtractedFieldRequest $request,
        int $documentId,
        int $fieldId
    ): JsonResponse {
        $document = Document::withTrashed()->findOrFail($documentId);
        $this->authz->ensure($request->user(), $document, true);

        $field = ExtractedField::query()
            ->whereKey($fieldId)
            ->whereHas(
                'extraction',
                fn ($query) => $query->where('document_id', $document->id)
            )
            ->firstOrFail();

        $decision = ReviewDecision::from($request->string('decision')->value());

        $field = $this->reviews->review(
            $request->user(),
            $field,
            $decision,
            $request->input('value'),
            $request->input('note'),
        );

        IndexDocumentForSemanticSearch::dispatch($document->id);

        $this->audit->record(
            'document.intelligence.field_reviewed',
            $request->user(),
            $document,
            [
                'field_id' => $field->id,
                'field_name' => $field->field_name,
                'decision' => $decision->value,
            ]
        );

        return ApiResponse::success([
            'field' => $this->serializeField($field),
        ]);
    }

    private function serializeExtraction($extraction): array
    {
        return [
            'id' => $extraction->id,
            'version_number' => $extraction->version_number,
            'status' => $this->enumValue($extraction->status),
            'document_type' => $extraction->document_type,
            'overall_confidence' => $extraction->overall_confidence,
            'extractor_version' => $extraction->extractor_version,
            'model_version' => $extraction->model_version,
            'validation_errors' => $extraction->validation_errors ?? [],
            'raw_payload' => $extraction->raw_payload,
            'fields' => $extraction->fields
                ->map(fn ($field) => $this->serializeField($field))
                ->values(),
            'created_at' => $extraction->created_at?->toIso8601String(),
            'finished_at' => $extraction->finished_at?->toIso8601String(),
        ];
    }

    private function serializeField($field): array
    {
        return [
            'id' => $field->id,
            'field_name' => $field->field_name,
            'value' => $field->value,
            'normalized_value' => $field->normalized_value,
            'confidence' => $field->confidence,
            'page' => $field->page,
            'evidence_text' => $field->evidence_text,
            'extractor_version' => $field->extractor_version,
            'model_version' => $field->model_version,
            'review_status' => $this->enumValue($field->review_status),
            'source' => $field->source,
            'evidence' => $field->evidence?->map(fn ($evidence) => [
                'id' => $evidence->id,
                'page' => $evidence->page,
                'evidence_text' => $evidence->evidence_text,
                'source' => $evidence->source,
            ])->values() ?? [],
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
