<?php

namespace App\Domain\Collaboration\Models;

use App\Domain\Collaboration\Enums\SharingScope;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedResource extends Model
{
    protected $fillable = [
        'household_id',
        'owner_user_id',
        'shared_by_user_id',
        'resource_type',
        'resource_id',
        'scope',
        'shared_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scope' => SharingScope::class,
            'shared_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}
