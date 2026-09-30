<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model {

 protected $table='asset_categories';
 protected $fillable=['household_id', 'name', 'slug'];
 protected function casts():array { return []; }
 public function assets(){return $this->hasMany(Asset::class);}
}
