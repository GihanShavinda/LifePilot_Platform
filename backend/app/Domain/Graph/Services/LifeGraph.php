<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Models\{LifeEntity,LifeRelation};

class LifeGraph
{
    public function entity(int $householdId,string $type,string $sourceType,int|string $sourceId,string $label,array $metadata=[],array $source=[]):LifeEntity
    {
        $key=$type.':'.$sourceType.':'.$sourceId;
        return LifeEntity::updateOrCreate(
            ['household_id'=>$householdId,'canonical_key'=>$key],
            ['entity_type'=>$type,'source_type'=>$sourceType,'source_id'=>(string)$sourceId,'label'=>$label,'search_text'=>$this->searchText($label,$metadata),'metadata'=>$metadata,'source_reference'=>$source]
        );
    }
    public function relate(int $householdId,LifeEntity $from,LifeEntity $to,string $type,array $metadata=[],array $source=[]):LifeRelation
    {
        abort_unless($from->household_id===$householdId && $to->household_id===$householdId,422,'Cross-household relations are forbidden.');
        $key=hash('sha256',implode('|',[$householdId,$from->id,$type,$to->id]));
        return LifeRelation::updateOrCreate(['dedupe_key'=>$key],['household_id'=>$householdId,'from_entity_id'=>$from->id,'to_entity_id'=>$to->id,'relation_type'=>$type,'metadata'=>$metadata,'source_reference'=>$source]);
    }
    public function neighbors(int $householdId,int $entityId,int $depth=1):array
    {
        $seen=[$entityId=>true]; $front=[$entityId]; $nodes=[]; $edges=[];
        for($d=0;$d<$depth && $front;$d++){
            $rels=LifeRelation::where('household_id',$householdId)->where(fn($q)=>$q->whereIn('from_entity_id',$front)->orWhereIn('to_entity_id',$front))->get();
            $next=[];
            foreach($rels as $r){$edges[$r->id]=$r; foreach([$r->from_entity_id,$r->to_entity_id] as $id){if(!isset($seen[$id])){$seen[$id]=true;$next[]=$id;}}}
            $front=$next;
        }
        $entities=LifeEntity::where('household_id',$householdId)->whereIn('id',array_keys($seen))->get();
        foreach($entities as $e)$nodes[]=$e;
        return ['nodes'=>$nodes,'edges'=>array_values($edges)];
    }
    private function searchText(string $label,array $metadata):string{return trim($label.' '.implode(' ',array_map(fn($v)=>is_scalar($v)?(string)$v:'', $metadata)));}
}
