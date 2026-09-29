<?php
namespace App\Domain\Users\Models;
use App\Domain\Auth\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HouseholdMember extends Model { use HasFactory; protected $fillable=['household_id','user_id','role']; protected function casts():array{return ['role'=>HouseholdRole::class];} public function household():BelongsTo{return $this->belongsTo(Household::class);} public function user():BelongsTo{return $this->belongsTo(User::class);} }
