<?php
namespace App\Domain\Documents\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DocumentProcessingJob extends Model { protected $fillable=['document_id','document_version_id','job_type','status','attempts','message','result','started_at','finished_at']; protected function casts():array{return ['result'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];} public function document():BelongsTo{return $this->belongsTo(Document::class);} public function version():BelongsTo{return $this->belongsTo(DocumentVersion::class,'document_version_id');} }
