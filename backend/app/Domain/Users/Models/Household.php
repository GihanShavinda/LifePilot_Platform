<?php
namespace App\Domain\Users\Models;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Household extends Model { use HasFactory; protected $fillable=['name']; public function memberships():HasMany{return $this->hasMany(HouseholdMember::class);} public function documents():HasMany{return $this->hasMany(Document::class);} public function documentTags():HasMany{return $this->hasMany(DocumentTag::class);} }
