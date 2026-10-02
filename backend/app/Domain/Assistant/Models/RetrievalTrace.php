<?php
namespace App\Domain\Assistant\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RetrievalTrace extends Model
{
    protected $fillable=['conversation_session_id','conversation_message_id','household_id','query','intent','retriever','filters','evidence','result_count','latency_ms','metadata'];
    protected function casts():array{return ['filters'=>'array','evidence'=>'array','metadata'=>'array'];}
    public function session():BelongsTo{return $this->belongsTo(ConversationSession::class,'conversation_session_id');}
}
