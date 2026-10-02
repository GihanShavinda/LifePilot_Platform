<?php
namespace App\Domain\Graph\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LifeEntity extends Model
{
    protected $fillable=['household_id','entity_type','source_type','source_id','canonical_key','label','search_text','metadata','source_reference'];
    protected function casts():array{return ['metadata'=>'array','source_reference'=>'array'];}
    public function outgoing():HasMany{return $this->hasMany(LifeRelation::class,'from_entity_id');}
    public function incoming():HasMany{return $this->hasMany(LifeRelation::class,'to_entity_id');}
}
