<?php
namespace App\Domain\Graph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasOne};

class DocumentChunk extends Model
{
    protected $fillable=['household_id','document_id','document_extraction_id','chunk_index','content','content_hash','metadata','source_reference'];
    protected function casts():array{return ['metadata'=>'array','source_reference'=>'array'];}
    public function embedding():HasOne{return $this->hasOne(EmbeddingRecord::class,'document_chunk_id');}
}
