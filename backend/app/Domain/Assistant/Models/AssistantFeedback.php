<?php
namespace App\Domain\Assistant\Models;
use Illuminate\Database\Eloquent\Model;
class AssistantFeedback extends Model
{
    protected $fillable=['conversation_message_id','user_id','rating','reason','comment'];
}
