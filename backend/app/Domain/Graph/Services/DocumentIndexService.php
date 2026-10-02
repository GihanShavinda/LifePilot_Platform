<?php
namespace App\Domain\Graph\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Graph\Contracts\EmbeddingProvider;
use App\Domain\Graph\Models\{DocumentChunk,EmbeddingRecord};
use Illuminate\Support\Facades\{DB,Schema};

class DocumentIndexService
{
    public function __construct(private DocumentChunker $chunker,private EmbeddingProvider $embeddings){}
    public function index(Document $document):int
    {
        $ex=$document->extractions()->latest('version_number')->first(); if(!$ex)return 0;
        $accepted=$ex->fields()->whereIn('review_status',['accepted','edited'])->exists(); if(!$accepted)return 0;
        $text=trim((string)$ex->source_text); if($text==='')return 0;
        $chunks=$this->chunker->chunk($text,(int)config('lifepilot_graph.chunk_size',900),(int)config('lifepilot_graph.chunk_overlap',150));
        DB::transaction(function()use($document,$ex,$chunks){
            DocumentChunk::where('document_id',$document->id)->delete();
            foreach($chunks as $i=>$content){
                $hash=hash('sha256',$content);
                $chunk=DocumentChunk::create(['household_id'=>$document->household_id,'document_id'=>$document->id,'document_extraction_id'=>$ex->id,'chunk_index'=>$i,'content'=>$content,'content_hash'=>$hash,'metadata'=>['document_title'=>$document->title,'mime_type'=>$document->mime_type],'source_reference'=>['type'=>'document_chunk','document_id'=>$document->id,'extraction_id'=>$ex->id,'chunk_index'=>$i]]);
                $vector=$this->embeddings->embed($content);
                $record=EmbeddingRecord::create(['household_id'=>$document->household_id,'document_chunk_id'=>$chunk->id,'provider'=>'local','model'=>$this->embeddings->model(),'dimensions'=>$this->embeddings->dimensions(),'embedding_json'=>json_encode($vector,JSON_PRESERVE_ZERO_FRACTION),'content_hash'=>$hash]);
                if(DB::getDriverName()==='pgsql' && Schema::hasColumn('embedding_records','embedding')){DB::statement('UPDATE embedding_records SET embedding = ?::vector WHERE id = ?',[$this->vectorLiteral($vector),$record->id]);}
            }
        });
        return count($chunks);
    }
    public function indexHousehold(int $householdId):array
    {
        $docs=Document::where('household_id',$householdId)->get();$count=0;$chunks=0;
        foreach($docs as $doc){$n=$this->index($doc);if($n){$count++;$chunks+=$n;}}
        return ['documents'=>$count,'chunks'=>$chunks];
    }
    private function vectorLiteral(array $v):string{return '['.implode(',',array_map(fn($x)=>sprintf('%.8F',$x),$v)).']';}
}
