<?php
namespace App\Domain\Assistant\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class ConversationSession extends Model
{
    protected $fillable=['household_id','user_id','title','status','last_message_at','metadata'];
    protected function casts():array{return ['last_message_at'=>'datetime','metadata'=>'array'];}
    public function user():BelongsTo{return $this->belongsTo(\App\Domain\Users\Models\User::class);}
    public function messages():HasMany{return $this->hasMany(ConversationMessage::class,'conversation_session_id')->orderBy('id');}
    public function traces():HasMany{return $this->hasMany(RetrievalTrace::class,'conversation_session_id');}
}
