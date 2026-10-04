<?php

namespace App\Domain\Users\Models;

use App\Domain\Collaboration\Models\{Assignment, HouseholdActivity, HouseholdInvitation, SharedResource};
use App\Domain\Documents\Models\{Document, DocumentTag};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function memberships(): HasMany { return $this->hasMany(HouseholdMember::class); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function documentTags(): HasMany { return $this->hasMany(DocumentTag::class); }
    public function tasks(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Task::class); }
    public function obligations(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Obligation::class); }
    public function expenses(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Expense::class); }
    public function subscriptions(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Subscription::class); }
    public function assets(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Asset::class); }
    public function invitations(): HasMany { return $this->hasMany(HouseholdInvitation::class); }
    public function sharedResources(): HasMany { return $this->hasMany(SharedResource::class); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class); }
    public function activities(): HasMany { return $this->hasMany(HouseholdActivity::class); }
}
