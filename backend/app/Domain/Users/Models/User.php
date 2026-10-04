<?php

namespace App\Domain\Users\Models;

use App\Domain\Collaboration\Models\{Assignment, HouseholdActivity, HouseholdInvitation, SharedResource};
use App\Domain\Documents\Models\Document;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany, HasOne};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, Notifiable, MustVerifyEmail;

    protected $fillable = ['name', 'email', 'password', 'timezone'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    protected static function newFactory(): UserFactory { return UserFactory::new(); }

    public function profile(): HasOne { return $this->hasOne(\App\Domain\Profiles\Models\Profile::class); }
    public function notificationPreference(): HasOne { return $this->hasOne(\App\Domain\Notifications\Models\NotificationPreference::class); }
    public function householdMemberships(): HasMany { return $this->hasMany(HouseholdMember::class); }
    public function households(): BelongsToMany { return $this->belongsToMany(Household::class, 'household_members')->withPivot(['role'])->withTimestamps(); }
    public function integrationConnections(): HasMany { return $this->hasMany(\App\Domain\Integrations\Models\IntegrationConnection::class); }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function tasks(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Task::class); }
    public function obligations(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Obligation::class); }
    public function expenses(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Expense::class); }
    public function subscriptions(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Subscription::class); }
    public function assets(): HasMany { return $this->hasMany(\App\Domain\Finance\Models\Asset::class); }
    public function sentHouseholdInvitations(): HasMany { return $this->hasMany(HouseholdInvitation::class, 'invited_by_user_id'); }
    public function ownedSharedResources(): HasMany { return $this->hasMany(SharedResource::class, 'owner_user_id'); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class, 'assignee_user_id'); }
    public function householdActivities(): HasMany { return $this->hasMany(HouseholdActivity::class, 'actor_user_id'); }
}
