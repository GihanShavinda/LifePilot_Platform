<?php

namespace App\Domain\Completion\Services;

use App\Domain\Documents\Models\{
    Document,
    ExtractedField
};
use App\Domain\Users\Models\User;

class DocumentDashboardService
{
    public function __construct(
        private readonly CompletionAccess $access
    ) {
    }

    public function build(User $user): array
    {
        $householdId = $this->access->householdId($user);

        /*
        |--------------------------------------------------------------------------
        | Accessible documents only
        |--------------------------------------------------------------------------
        |
        | P10 privacy rules remain authoritative here.
        |
        | A document is included only when the current user can access it.
        | This prevents another household member's private document from being
        | counted in P12 document dashboard statistics.
        |
        */

        $documents = Document::accessibleTo($user)
            ->where('household_id', $householdId)
            ->with([
                'category:id,name',

                /*
                |--------------------------------------------------------------------------
                | Latest extraction
                |--------------------------------------------------------------------------
                |
                | latestExtraction is a latestOfMany relationship.
                |
                | Laravel generates internal joins for this relation. Therefore all
                | selected extraction columns must be fully qualified; otherwise
                | SQLite/PostgreSQL may report an ambiguous document_id column.
                |
                */

                'latestExtraction' => function ($query) {
                    $query->select([
                        'document_extractions.id',
                        'document_extractions.document_id',
                        'document_extractions.status',
                        'document_extractions.document_type',
                        'document_extractions.overall_confidence',
                    ]);
                },
            ])
            ->get();

        $documentIds = $documents
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Processing-state breakdown
        |--------------------------------------------------------------------------
        */

        $processing = $documents
            ->groupBy(function (Document $document) {
                if (is_object($document->processing_status)) {
                    return $document->processing_status->value;
                }

                return (string) $document->processing_status;
            })
            ->map(
                fn ($items, $status) => [
                    'status' => $status ?: 'unknown',
                    'count' => $items->count(),
                ]
            )
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Document categories
        |--------------------------------------------------------------------------
        */

        $categories = $documents
            ->groupBy(
                fn (Document $document) =>
                    $document->category?->name ?? 'Uncategorized'
            )
            ->map(
                fn ($items, $category) => [
                    'category' => $category,
                    'count' => $items->count(),
                ]
            )
            ->sortByDesc('count')
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Extraction review queue
        |--------------------------------------------------------------------------
        |
        | Important:
        | This query is restricted to extraction fields belonging only to
        | documents already proven accessible to the authenticated user.
        |
        */

        $reviewQueue = collect();

        if ($documentIds->isNotEmpty()) {
            $reviewQueue = ExtractedField::query()
                ->whereIn(
                    'review_status',
                    [
                        'pending',
                        'needs_review',
                    ]
                )
                ->whereHas(
                    'extraction.document',
                    function ($query) use ($documentIds) {
                        $query->whereIn(
                            'documents.id',
                            $documentIds->all()
                        );
                    }
                )
                ->with([
                    'extraction' => function ($query) {
                        $query->select([
                            'document_extractions.id',
                            'document_extractions.document_id',
                        ]);
                    },

                    'extraction.document' => function ($query) {
                        $query->select([
                            'documents.id',
                            'documents.title',
                            'documents.original_filename',
                        ]);
                    },
                ])
                ->orderBy('confidence')
                ->limit(30)
                ->get()
                ->map(
                    fn (ExtractedField $field) => [
                        'field_id' =>
                            $field->id,

                        'document_id' =>
                            $field->extraction?->document_id,

                        'document_title' =>
                            $field->extraction?->document?->title
                            ?? $field->extraction?->document?->original_filename,

                        'field_name' =>
                            $field->field_name,

                        'confidence' =>
                            $field->confidence,

                        'review_status' =>
                            is_object($field->review_status)
                                ? $field->review_status->value
                                : $field->review_status,

                        'evidence_text' =>
                            $field->evidence_text,

                        'source_ref' =>
                            'extraction_field:' . $field->id,
                    ]
                )
                ->values();
        }

        return [
            'total_documents' =>
                $documents->count(),

            'processing_state' =>
                $processing,

            'categories' =>
                $categories,

            'extraction_review_queue' =>
                $reviewQueue->all(),

            'review_queue_count' =>
                $reviewQueue->count(),

            'generated_at' =>
                now()->toIso8601String(),
        ];
    }
}