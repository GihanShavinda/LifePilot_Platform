<?php
namespace App\Domain\Graph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeRelation extends Model
{
    protected $fillable=['household_id','from_entity_id','to_entity_id','relation_type','dedupe_key','metadata','source_reference'];
    protected function casts():array{return ['metadata'=>'array','source_reference'=>'array'];}
    public function from():BelongsTo{return $this->belongsTo(LifeEntity::class,'from_entity_id');}
    public function to():BelongsTo{return $this->belongsTo(LifeEntity::class,'to_entity_id');}
}
