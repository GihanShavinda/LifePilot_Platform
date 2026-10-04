<?php

namespace App\Domain\Documents\Controllers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentCategory;
use App\Domain\Documents\Requests\ReplaceDocumentRequest;
use App\Domain\Documents\Requests\UpdateDocumentRequest;
use App\Domain\Documents\Requests\UploadDocumentRequest;
use App\Domain\Documents\Resources\DocumentResource;
use App\Domain\Documents\Services\DocumentAuthorization;
use App\Domain\Documents\Services\DocumentService;
use App\Domain\Documents\Services\DocumentStorageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController
{
    public function __construct(
        private DocumentAuthorization $authz,
        private DocumentService $service,
        private DocumentStorageService $storage,
        private AuditService $audit
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Document::query()
            ->accessibleTo($user)
            ->with([
                'category',
                'source',
                'tags',
                'latestExtraction',
            ]);

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        } else {
            $query->withoutTrashed();
        }

        $search = $request->string('search')->trim()->value();

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $pattern = '%'.$search.'%';

                $subQuery
                    ->whereLike('title', $pattern, caseSensitive: false)
                    ->orWhereLike('original_filename', $pattern, caseSensitive: false)
                    ->orWhereLike('issuer', $pattern, caseSensitive: false);
            });
        }

        if ($category = $request->get('category')) {
            $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $category)
            );
        }

        if ($issuer = $request->get('issuer')) {
            $query->whereLike(
                'issuer',
                '%'.$issuer.'%',
                caseSensitive: false
            );
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('document_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('document_date', '<=', $dateTo);
        }

        $perPage = min(
            max((int) $request->get('per_page', 15), 1),
            100
        );

        $page = $query->latest()->paginate($perPage);

        return ApiResponse::success(
            DocumentResource::collection($page->items()),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ]
        );
    }

    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->authz->householdId($user);

        $document = $this->service->upload(
            $user,
            $householdId,
            $request->file('file'),
            $request->validated()
        );

        $this->audit->record(
            'document.uploaded',
            $user,
            $document,
            [
                'checksum' => $document->checksum,
                'mime_type' => $document->mime_type,
            ]
        );

        return ApiResponse::success([
            'document' => new DocumentResource($document),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::withTrashed()
            ->with([
                'category',
                'source',
                'tags',
                'versions',
                'latestExtraction',
            ])
            ->findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document
        );

        return ApiResponse::success([
            'document' => new DocumentResource($document),
        ]);
    }

    public function update(
        UpdateDocumentRequest $request,
        int $id
    ): JsonResponse {
        $document = Document::withTrashed()
            ->findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document,
            true
        );

        $data = $request->validated();

        if (array_key_exists('category', $data)) {
            $document->document_category_id = $data['category']
                ? DocumentCategory::where(
                    'slug',
                    $data['category']
                )->value('id')
                : null;
        }

        foreach (['title', 'document_date', 'issuer'] as $field) {
            if (array_key_exists($field, $data)) {
                $document->{$field} = $data[$field];
            }
        }

        $document->save();

        if (array_key_exists('tags', $data)) {
            $this->service->syncTags(
                $document,
                $data['tags'],
                $document->household_id
            );
        }

        $this->audit->record(
            'document.updated',
            $request->user(),
            $document
        );

        return ApiResponse::success([
            'document' => new DocumentResource(
                $document->fresh([
                    'category',
                    'source',
                    'tags',
                    'versions',
                    'latestExtraction',
                ])
            ),
        ]);
    }

    public function replace(
        ReplaceDocumentRequest $request,
        int $id
    ): JsonResponse {
        $document = Document::findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document,
            true
        );

        $document = $this->service->replace(
            $document,
            $request->user(),
            $request->file('file')
        );

        $this->audit->record(
            'document.replaced',
            $request->user(),
            $document,
            [
                'version' => $document
                    ->versions()
                    ->max('version_number'),
            ]
        );

        return ApiResponse::success([
            'document' => new DocumentResource($document),
        ]);
    }

    public function archive(
        Request $request,
        int $id
    ): JsonResponse {
        $document = Document::findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document,
            true
        );

        $document->update([
            'status' => DocumentStatus::Archived,
            'archived_at' => now(),
        ]);

        $this->audit->record(
            'document.archived',
            $request->user(),
            $document
        );

        return ApiResponse::success([
            'document' => new DocumentResource($document),
        ]);
    }

    public function restoreArchive(
        Request $request,
        int $id
    ): JsonResponse {
        $document = Document::withTrashed()
            ->findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document,
            true
        );

        if ($document->trashed()) {
            $document->restore();
        }

        $document->update([
            'status' => DocumentStatus::Active,
            'archived_at' => null,
        ]);

        $this->audit->record(
            'document.restored',
            $request->user(),
            $document
        );

        return ApiResponse::success([
            'document' => new DocumentResource($document),
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $document = Document::findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document,
            true
        );

        $this->audit->record(
            'document.deleted',
            $request->user(),
            $document
        );

        $document->delete();

        return ApiResponse::success([
            'message' => 'Document moved to trash.',
        ]);
    }

    public function signedUrl(
        Request $request,
        int $id
    ): JsonResponse {
        $document = Document::findOrFail($id);

        $this->authz->ensure(
            $request->user(),
            $document
        );

        $version = $document
            ->versions()
            ->latest('version_number')
            ->firstOrFail();

        $url = $this->storage->signedUrl($version);

        $this->audit->record(
            'document.download_link_created',
            $request->user(),
            $document,
            [
                'version_id' => $version->id,
            ]
        );

        return ApiResponse::success([
            'url' => $url,
            'expires_in_seconds' => 600,
        ]);
    }

    public function download(
        Request $request,
        int $document,
        int $version
    ): StreamedResponse {
        $documentModel = Document::findOrFail($document);

        $this->authz->ensure(
            $request->user(),
            $documentModel
        );

        $documentVersion = $documentModel
            ->versions()
            ->whereKey($version)
            ->firstOrFail();

        $this->audit->record(
            'document.downloaded',
            $request->user(),
            $documentModel,
            [
                'version_id' => $documentVersion->id,
            ]
        );

        $disk = Storage::disk($documentVersion->storage_disk);
        $path = $documentVersion->storage_path;

        return response()->streamDownload(
            static function () use ($disk, $path): void {
                $stream = $disk->readStream($path);

                if (! is_resource($stream)) {
                    abort(404);
                }

                fpassthru($stream);
                fclose($stream);
            },
            $documentVersion->original_filename,
            [
                'Content-Type' => $documentVersion->mime_type,
            ]
        );
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success([
            'categories' => DocumentCategory::orderBy('name')
                ->get(['slug', 'name']),
        ]);
    }
}
