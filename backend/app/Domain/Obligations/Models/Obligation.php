<?php
namespace App\Domain\Obligations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

class Obligation extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id','household_id','document_id','document_extraction_id','type','title','description','amount','currency','due_at','status','source_evidence','dedupe_key','approved_at','approved_by'];
    protected function casts(): array { return ['due_at'=>'datetime','approved_at'=>'datetime','source_evidence'=>'array','amount'=>'decimal:2']; }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function document(): BelongsTo { return $this->belongsTo(\App\Domain\Documents\Models\Document::class); }
}
