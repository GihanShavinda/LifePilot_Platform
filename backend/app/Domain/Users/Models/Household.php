<?php
namespace App\Domain\Users\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Household extends Model { use HasFactory; protected $fillable=['name']; public function memberships():HasMany{return $this->hasMany(HouseholdMember::class);} }
