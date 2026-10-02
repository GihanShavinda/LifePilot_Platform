<?php
namespace App\Domain\Assistant\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ConversationMessage extends Model
{
    protected $fillable=['conversation_session_id','user_id','role','intent','content','citations','claims','model_provider','model_name','model_version','grounding_status','metadata'];
    protected function casts():array{return ['citations'=>'array','claims'=>'array','metadata'=>'array'];}
    public function session():BelongsTo{return $this->belongsTo(ConversationSession::class,'conversation_session_id');}
}
