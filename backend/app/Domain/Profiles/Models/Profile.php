<?php
namespace App\Domain\Profiles\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Profile extends Model { use HasFactory; protected $fillable=['user_id','first_name','last_name','phone','date_of_birth','locale']; protected function casts():array{return ['date_of_birth'=>'date'];} public function user():BelongsTo{return $this->belongsTo(\App\Domain\Users\Models\User::class);} }
