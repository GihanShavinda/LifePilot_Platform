<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Models\LifeEntity;
use Carbon\CarbonImmutable;

class StructuredLifeQueryService
{
    public function __construct(private LifeGraph $graph){}

    public function query(int $householdId,string $query):array
    {
        $q=mb_strtolower($query);$rows=[];
        if(str_contains($q,'warrant') && (str_contains($q,'expir')||str_contains($q,'year'))){
            $year=now()->year;
            $rows=LifeEntity::where(['household_id'=>$householdId,'entity_type'=>'warranty'])->get()->filter(fn($e)=>($e->metadata['end_date']??null) && CarbonImmutable::parse($e->metadata['end_date'])->year===$year)->values()->all();
        } elseif((str_contains($q,'payment')||str_contains($q,'due')) && str_contains($q,'next week')){
            $start=now()->addWeek()->startOfWeek();$end=$start->endOfWeek();
            $rows=LifeEntity::where('household_id',$householdId)->whereIn('entity_type',['obligation','task','subscription'])->get()->filter(function($e)use($start,$end){$d=$e->metadata['due_at']??$e->metadata['next_billing_date']??null;return $d && CarbonImmutable::parse($d)->between($start,$end);})->values()->all();
        } elseif(str_contains($q,'university') && str_contains($q,'task')){
            $rows=LifeEntity::where(['household_id'=>$householdId,'entity_type'=>'task'])->whereRaw('LOWER(search_text) LIKE ?', ['%university%'])->get()->all();
        } elseif(str_contains($q,'car')){
            $roots=LifeEntity::where('household_id',$householdId)->where(fn($x)=>$x->whereRaw('LOWER(label) LIKE ?', ['%car%'])->orWhereRaw('LOWER(search_text) LIKE ?', ['%car%']))->get();
            $rows=$this->expand($householdId,$roots->all(),2);
        } elseif(str_contains($q,'laptop') && str_contains($q,'receipt')){
            $roots=LifeEntity::where('household_id',$householdId)->where(fn($x)=>$x->whereRaw('LOWER(label) LIKE ?', ['%laptop%'])->orWhereRaw('LOWER(search_text) LIKE ?', ['%laptop%']))->get();
            $rows=$this->expand($householdId,$roots->all(),2);
        }
        return array_map(fn($e)=>['kind'=>'structured_entity','id'=>$e->id,'score'=>1.0,'title'=>$e->label,'snippet'=>$e->search_text,'metadata'=>array_merge(['entity_type'=>$e->entity_type],$e->metadata??[]),'sources'=>[$e->source_reference ?: ['type'=>$e->source_type,'id'=>$e->source_id]]],$rows);
    }

    private function expand(int $householdId,array $roots,int $depth):array
    {
        $all=[];
        foreach($roots as $root){$all[$root->id]=$root;foreach($this->graph->neighbors($householdId,$root->id,$depth)['nodes'] as $node)$all[$node->id]=$node;}
        return array_values($all);
    }
}
