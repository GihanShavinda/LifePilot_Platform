<?php
namespace App\Domain\Documents\Models;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DocumentVersion extends Model { protected $fillable=['document_id','uploaded_by','version_number','original_filename','mime_type','size','storage_disk','storage_path','checksum']; public function document():BelongsTo{return $this->belongsTo(Document::class);} public function uploader():BelongsTo{return $this->belongsTo(User::class,'uploaded_by');} }
