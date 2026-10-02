<?php
namespace App\Domain\Graph\Controllers;
use App\Domain\Documents\Models\Document;
use App\Domain\Graph\Jobs\IndexDocumentForSemanticSearch;
use App\Domain\Graph\Services\{GraphAccess,GraphSyncService,HybridSearchService,StructuredLifeQueryService};
use Illuminate\Http\{JsonResponse,Request};
class SemanticSearchController
{
    public function __construct(private GraphAccess $access,private GraphSyncService $sync,private HybridSearchService $search,private StructuredLifeQueryService $structured){}
    public function search(Request $r):JsonResponse
    {
        $data=$r->validate(['q'=>'required|string|min:2|max:500','entity_type'=>'nullable|string|max:40','document_id'=>'nullable|integer','limit'=>'nullable|integer|min:1|max:50']);$h=$this->access->householdId($r->user());$this->sync->syncHousehold($h);
        if(isset($data['document_id']))Document::where(['household_id'=>$h,'id'=>$data['document_id']])->firstOrFail();
        $structured=$this->structured->query($h,$data['q']);$hybrid=$this->search->search($h,$data['q'],array_filter(['entity_type'=>$data['entity_type']??null,'document_id'=>$data['document_id']??null]),$data['limit']??15);
        $results=[];$seen=[];foreach(array_merge($structured,$hybrid) as $item){$key=$item['kind'].':'.$item['id'];if(isset($seen[$key]))continue;$seen[$key]=true;if(empty($item['sources']))continue;$results[]=$item;if(count($results)>=($data['limit']??15))break;}
        return response()->json(['success'=>true,'data'=>['query'=>$data['q'],'results'=>$results,'source_policy'=>'Every result contains one or more source references.']]);
    }
    public function reindexDocument(Request $r,int $id):JsonResponse{$h=$this->access->householdId($r->user());$d=Document::where(['household_id'=>$h,'id'=>$id])->firstOrFail();IndexDocumentForSemanticSearch::dispatch($d->id);return response()->json(['success'=>true,'data'=>['queued'=>true,'document_id'=>$d->id]],202);}
}
