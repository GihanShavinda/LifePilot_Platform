<?php

namespace App\Domain\Graph\Controllers;

use App\Domain\Collaboration\Services\SharedResourceService;
use App\Domain\Graph\Models\LifeEntity;
use App\Domain\Graph\Services\{DocumentIndexService, GraphAccess, GraphSyncService, LifeGraph};
use Illuminate\Http\{JsonResponse, Request};

class GraphController
{
    public function __construct(
        private GraphAccess $access,
        private GraphSyncService $sync,
        private LifeGraph $graph,
        private DocumentIndexService $index,
        private SharedResourceService $sharing,
    ) {
    }

    public function sync(Request $r): JsonResponse
    {
        $h = $this->access->householdId($r->user());
        return response()->json(['success' => true, 'data' => [
            'graph' => $this->sync->syncHousehold($h),
            'semantic' => $this->index->indexHousehold($h),
        ]]);
    }

    public function entities(Request $r): JsonResponse
    {
        $h = $this->access->householdId($r->user());
        $query = LifeEntity::where('household_id', $h)
            ->when($r->string('type')->value(), fn ($q, $t) => $q->where('entity_type', $t))
            ->orderBy('label');

        $visible = $query->get()->filter(fn (LifeEntity $e) => $this->entityVisible($r, $e))->values();
        return response()->json(['success' => true, 'data' => ['entities' => $visible]]);
    }

    public function show(Request $r, int $id): JsonResponse
    {
        $h = $this->access->householdId($r->user());
        $e = LifeEntity::where(['household_id' => $h, 'id' => $id])->firstOrFail();
        abort_unless($this->entityVisible($r, $e), 404);

        $graph = $this->graph->neighbors($h, $e->id, min(3, max(1, (int) $r->integer('depth', 1))));
        $graph['nodes'] = collect($graph['nodes'] ?? [])->filter(fn ($node) => $this->entityVisible($r, $node))->values()->all();

        return response()->json(['success' => true, 'data' => ['entity' => $e, 'graph' => $graph]]);
    }

    private function entityVisible(Request $request, LifeEntity $entity): bool
    {
        $source = $entity->source_reference ?: ['type' => $entity->source_type, 'id' => $entity->source_id];
        return is_array($source) && $this->sharing->canAccessSource($request->user(), $source);
    }
}
