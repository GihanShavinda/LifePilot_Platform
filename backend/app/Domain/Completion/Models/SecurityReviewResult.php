<?php

namespace App\Domain\Completion\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityReviewResult extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'check_key', 'status', 'title', 'description',
        'evidence', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
