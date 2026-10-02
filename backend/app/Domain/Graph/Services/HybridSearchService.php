<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Contracts\EmbeddingProvider;
use App\Domain\Graph\Models\{DocumentChunk,EmbeddingRecord,LifeEntity};
use Illuminate\Support\Facades\{DB,Schema};

class HybridSearchService
{
    public function __construct(private EmbeddingProvider $embeddings){}
    public function search(int $householdId,string $query,array $filters=[],int $limit=15):array
    {
        $qv=$this->embeddings->embed($query);$keywords=$this->tokens($query);$rows=[];
        $chunks=DocumentChunk::where('household_id',$householdId)->when($filters['document_id']??null,fn($q,$id)=>$q->where('document_id',$id))->with('embedding')->get();
        $pgScores=[];
        if(DB::getDriverName()==='pgsql' && Schema::hasColumn('embedding_records','embedding')){
            $literal='['.implode(',',array_map(fn($x)=>sprintf('%.8F',$x),$qv)).']';
            $pgScores=DB::table('embedding_records')->where('household_id',$householdId)->select('document_chunk_id')->selectRaw('1 - (embedding <=> ?::vector) AS similarity',[$literal])->orderByDesc('similarity')->limit(max($limit*4,50))->pluck('similarity','document_chunk_id')->map(fn($v)=>(float)$v)->all();
        }
        foreach($chunks as $c){$vector=$c->embedding?->vector() ?: [];$cos=$pgScores[$c->id] ?? $this->cosine($qv,$vector);$kw=$this->keywordScore($keywords,$c->content);$score=0.72*$cos+0.28*$kw;$rows[]=['kind'=>'document_chunk','id'=>$c->id,'score'=>round($score,6),'title'=>$c->metadata['document_title'] ?? 'Document','snippet'=>$this->snippet($c->content,$keywords),'metadata'=>$c->metadata,'sources'=>[$c->source_reference ?: ['type'=>'document_chunk','document_id'=>$c->document_id,'chunk_id'=>$c->id]]];}
        $entities=LifeEntity::where('household_id',$householdId)->when($filters['entity_type']??null,fn($q,$t)=>$q->where('entity_type',$t))->get();
        foreach($entities as $e){$kw=$this->keywordScore($keywords,$e->label.' '.$e->search_text);if($kw<=0)continue;$rows[]=['kind'=>'life_entity','id'=>$e->id,'score'=>round(0.55+0.45*$kw,6),'title'=>$e->label,'snippet'=>$e->search_text,'metadata'=>array_merge(['entity_type'=>$e->entity_type],$e->metadata??[]),'sources'=>[$e->source_reference ?: ['type'=>$e->source_type,'id'=>$e->source_id]]];}
        usort($rows,fn($a,$b)=>$b['score']<=>$a['score']);return array_slice($rows,0,$limit);
    }
    private function tokens(string $q):array{return array_values(array_unique(array_filter(preg_split('/[^\\pL\\pN]+/u',mb_strtolower($q),-1,PREG_SPLIT_NO_EMPTY) ?: [],fn($x)=>mb_strlen($x)>2)));}
    private function keywordScore(array $tokens,string $text):float{if(!$tokens)return 0.0;$t=mb_strtolower($text);$hits=0;foreach($tokens as $x)if(str_contains($t,$x))$hits++;return min(1.0,$hits/max(1,count($tokens)));}
    private function cosine(array $a,array $b):float{if(!$a||count($a)!==count($b))return 0.0;$dot=0;$aa=0;$bb=0;foreach($a as $i=>$x){$y=(float)$b[$i];$dot+=$x*$y;$aa+=$x*$x;$bb+=$y*$y;}return ($aa>0&&$bb>0)?max(-1,min(1,$dot/(sqrt($aa)*sqrt($bb)))):0.0;}
    private function snippet(string $text,array $tokens):string{$low=mb_strtolower($text);$pos=null;foreach($tokens as $t){$p=mb_strpos($low,$t);if($p!==false){$pos=$p;break;}}$start=max(0,(int)($pos??0)-120);return mb_substr($text,$start,360);}
}
