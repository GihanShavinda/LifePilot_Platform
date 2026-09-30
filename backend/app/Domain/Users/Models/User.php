<?php

namespace App\Domain\Users\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\Asset;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Subscription;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Obligations\Models\Obligation;
use App\Domain\Obligations\Models\Task;
use App\Domain\Profiles\Models\Profile;

use Database\Factories\UserFactory;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use MustVerifyEmail;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Use the P1 custom user factory.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * P1: User profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * P1: Notification preferences.
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(
            NotificationPreference::class
        );
    }

    /**
     * Household membership records.
     */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(
            HouseholdMember::class,
            'user_id'
        );
    }

    /**
     * Households accessible to the user.
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(
            Household::class,
            'household_members',
            'user_id',
            'household_id'
        )
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * P1: External integration connections.
     */
    public function integrationConnections(): HasMany
    {
        return $this->hasMany(
            IntegrationConnection::class
        );
    }

    /**
     * P2: Uploaded documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'user_id'
        );
    }

    /**
     * P4: User tasks.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'user_id'
        );
    }

    /**
     * P4: User obligations.
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(
            Obligation::class,
            'user_id'
        );
    }

    /**
     * P5: User expenses.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(
            Expense::class,
            'user_id'
        );
    }

    /**
     * P5: User subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class,
            'user_id'
        );
    }

    /**
     * P5: User assets.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(
            Asset::class,
            'user_id'
        );
    }
}