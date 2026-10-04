<?php

namespace App\Domain\Graph\Controllers;

use App\Domain\Collaboration\Services\SharedResourceService;
use App\Domain\Documents\Models\Document;
use App\Domain\Graph\Jobs\IndexDocumentForSemanticSearch;
use App\Domain\Graph\Services\{GraphAccess, GraphSyncService, HybridSearchService, StructuredLifeQueryService};
use Illuminate\Http\{JsonResponse, Request};

class SemanticSearchController
{
    public function __construct(
        private GraphAccess $access,
        private GraphSyncService $sync,
        private HybridSearchService $search,
        private StructuredLifeQueryService $structured,
        private SharedResourceService $sharing,
    ) {
    }

    public function search(Request $r): JsonResponse
    {
        $data = $r->validate([
            'q' => 'required|string|min:2|max:500',
            'entity_type' => 'nullable|string|max:40',
            'document_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $h = $this->access->householdId($r->user());
        $this->sync->syncHousehold($h);

        if (isset($data['document_id'])) {
            Document::accessibleTo($r->user())->where(['household_id' => $h, 'id' => $data['document_id']])->firstOrFail();
        }

        $structured = $this->structured->query($h, $data['q']);
        $hybrid = $this->search->search($h, $data['q'], array_filter([
            'entity_type' => $data['entity_type'] ?? null,
            'document_id' => $data['document_id'] ?? null,
        ]), max(($data['limit'] ?? 15) * 3, 30));

        $results = [];
        $seen = [];
        foreach (array_merge($structured, $hybrid) as $item) {
            $sources = array_values(array_filter(
                $item['sources'] ?? [],
                fn (array $source) => $this->sharing->canAccessSource($r->user(), $source)
            ));
            if (!$sources) {
                continue;
            }

            $key = $item['kind'].':'.$item['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $item['sources'] = $sources;
            $results[] = $item;

            if (count($results) >= ($data['limit'] ?? 15)) {
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $data['q'],
                'results' => $results,
                'source_policy' => 'Results are restricted to private records owned by the requester plus explicitly household-shared resources.',
            ],
        ]);
    }

    public function reindexDocument(Request $r, int $id): JsonResponse
    {
        $h = $this->access->householdId($r->user());
        $d = Document::accessibleTo($r->user())->where(['household_id' => $h, 'id' => $id])->firstOrFail();
        $this->sharing->ensureRead($r->user(), $d);
        IndexDocumentForSemanticSearch::dispatch($d->id);
        return response()->json(['success' => true, 'data' => ['queued' => true, 'document_id' => $d->id]], 202);
    }
}
