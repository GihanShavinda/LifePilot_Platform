<?php
namespace App\Domain\Documents\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class DocumentTag extends Model { protected $fillable=['household_id','name','slug']; public function documents():BelongsToMany{return $this->belongsToMany(Document::class,'document_document_tag');} }
