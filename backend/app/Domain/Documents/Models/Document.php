<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ProcessingStatus;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'document_category_id',
        'document_source_id',
        'title',
        'original_filename',
        'mime_type',
        'size',
        'storage_disk',
        'storage_path',
        'checksum',
        'document_date',
        'issuer',
        'status',
        'processing_status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'archived_at' => 'datetime',
            'status' => DocumentStatus::class,
            'processing_status' => ProcessingStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DocumentSource::class, 'document_source_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)
            ->orderByDesc('version_number');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentTag::class,
            'document_document_tag'
        )->withTimestamps();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(DocumentProcessingJob::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(DocumentExtraction::class)
            ->orderByDesc('version_number');
    }

    public function latestExtraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class)
            ->latestOfMany('version_number');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $householdIds = $user->householdMemberships()
            ->pluck('household_id');

        return $query->whereIn('household_id', $householdIds);
    }
    public function tasks(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Task::class); }
    public function obligations(): HasMany { return $this->hasMany(\App\Domain\Obligations\Models\Obligation::class); }
}
