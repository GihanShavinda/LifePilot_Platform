<?php
namespace App\Domain\Graph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbeddingRecord extends Model
{
    protected $fillable=['household_id','document_chunk_id','provider','model','dimensions','embedding_json','content_hash'];
    protected function casts():array{return ['dimensions'=>'integer'];}
    public function chunk():BelongsTo{return $this->belongsTo(DocumentChunk::class,'document_chunk_id');}
    public function vector():array{return json_decode($this->embedding_json ?: '[]',true) ?: [];}
}
