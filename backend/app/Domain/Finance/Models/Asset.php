<?php

namespace App\Domain\Finance\Models;

use App\Domain\Collaboration\Traits\HasHouseholdSharing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes, HasHouseholdSharing;

    protected $table = 'assets';
    protected $fillable = [
        'household_id',
        'user_id',
        'asset_category_id',
        'merchant_id',
        'document_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'currency',
        'location',
        'status',
    ];

    public static function sharingResourceType(): string
    {
        return 'asset';
    }

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'purchase_date' => 'date'];
    }
    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
    public function warranties()
    {
        return $this->hasMany(Warranty::class);
    }
    public function maintenance()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
    public function document()
    {
        return $this->belongsTo(\App\Domain\Documents\Models\Document::class);
    }
}
