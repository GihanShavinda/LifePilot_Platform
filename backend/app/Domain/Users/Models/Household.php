<?php

namespace App\Domain\Users\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTag;
use App\Domain\Finance\Models\Asset;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Subscription;
use App\Domain\Obligations\Models\Obligation;
use App\Domain\Obligations\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Household memberships.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            HouseholdMember::class,
            'household_id'
        );
    }

    /**
     * Users belonging to this household.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'household_members',
            'household_id',
            'user_id'
        )
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * P2: Household documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'household_id'
        );
    }

    /**
     * P2: Household document tags.
     */
    public function documentTags(): HasMany
    {
        return $this->hasMany(
            DocumentTag::class,
            'household_id'
        );
    }

    /**
     * P4: Household tasks.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'household_id'
        );
    }

    /**
     * P4: Household obligations.
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(
            Obligation::class,
            'household_id'
        );
    }

    /**
     * P5: Household expenses.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(
            Expense::class,
            'household_id'
        );
    }

    /**
     * P5: Household subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class,
            'household_id'
        );
    }

    /**
     * P5: Household assets.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(
            Asset::class,
            'household_id'
        );
    }
}