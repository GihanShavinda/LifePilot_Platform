<?php

namespace App\Domain\Users\Models;

use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Notifications\Models\NotificationPreference;
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

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
    ];

    /**
     * Attributes hidden from API serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Explicitly map this domain model to its factory.
     *
     * Without this Laravel attempts to find:
     * Database\Factories\Domain\Users\Models\UserFactory
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * User profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Notification preferences.
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Household memberships belonging to this user.
     */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /**
     * Households this user belongs to.
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(
            Household::class,
            'household_members'
        )
            ->withPivot([
                'role',
            ])
            ->withTimestamps();
    }

    /**
     * External integration connections.
     */
    public function integrationConnections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class);
    }
}
