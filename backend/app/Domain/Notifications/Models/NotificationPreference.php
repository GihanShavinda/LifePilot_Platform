<?php
namespace App\Domain\Notifications\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class NotificationPreference extends Model { protected $fillable=['user_id','email_enabled','push_enabled','reminder_enabled','digest_enabled']; protected function casts():array{return ['email_enabled'=>'boolean','push_enabled'=>'boolean','reminder_enabled'=>'boolean','digest_enabled'=>'boolean'];} public function user():BelongsTo{return $this->belongsTo(\App\Domain\Users\Models\User::class);} }
