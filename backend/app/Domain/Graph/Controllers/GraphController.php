<?php
namespace App\Domain\Graph\Controllers;
use App\Domain\Graph\Models\LifeEntity;
use App\Domain\Graph\Services\{GraphAccess,GraphSyncService,LifeGraph,DocumentIndexService};
use Illuminate\Http\{JsonResponse,Request};
class GraphController
{
    public function __construct(private GraphAccess $access,private GraphSyncService $sync,private LifeGraph $graph,private DocumentIndexService $index){}
    public function sync(Request $r):JsonResponse{$h=$this->access->householdId($r->user());return response()->json(['success'=>true,'data'=>['graph'=>$this->sync->syncHousehold($h),'semantic'=>$this->index->indexHousehold($h)]]);}
    public function entities(Request $r):JsonResponse{$h=$this->access->householdId($r->user());$items=LifeEntity::where('household_id',$h)->when($r->string('type')->value(),fn($q,$t)=>$q->where('entity_type',$t))->orderBy('label')->paginate(50);return response()->json(['success'=>true,'data'=>['entities'=>$items]]);}
    public function show(Request $r,int $id):JsonResponse{$h=$this->access->householdId($r->user());$e=LifeEntity::where(['household_id'=>$h,'id'=>$id])->firstOrFail();return response()->json(['success'=>true,'data'=>['entity'=>$e,'graph'=>$this->graph->neighbors($h,$e->id,min(3,max(1,(int)$r->integer('depth',1))))]]);}
}
