<?php

namespace App\Domain\Collaboration\Traits;

use App\Domain\Collaboration\Models\SharedResource;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasHouseholdSharing
{
    abstract public static function sharingResourceType(): string;

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $table = $query->getModel()->getTable();
        $householdIds = $user->householdMemberships()->pluck('household_id');

        return $query
            ->whereIn($table.'.household_id', $householdIds)
            ->where(function (Builder $visibility) use ($table, $user) {
                $visibility
                    ->where($table.'.user_id', $user->id)
                    ->orWhereExists(function ($shared) use ($table) {
                        $shared
                            ->selectRaw('1')
                            ->from('shared_resources')
                            ->whereColumn('shared_resources.household_id', $table.'.household_id')
                            ->whereColumn('shared_resources.resource_id', $table.'.id')
                            ->where('shared_resources.resource_type', static::sharingResourceType())
                            ->where('shared_resources.scope', 'household');
                    });
            });
    }

    public function sharingRecord(): ?SharedResource
    {
        return SharedResource::query()
            ->where('household_id', $this->getAttribute('household_id'))
            ->where('resource_type', static::sharingResourceType())
            ->where('resource_id', $this->getKey())
            ->first();
    }
}
