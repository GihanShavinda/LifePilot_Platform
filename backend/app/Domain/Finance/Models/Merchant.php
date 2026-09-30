<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model {

 protected $table='merchants';
 protected $fillable=['household_id', 'name', 'normalized_name'];
 protected function casts():array { return []; }
 public function expenses(){return $this->hasMany(Expense::class);} public function subscriptions(){return $this->hasMany(Subscription::class);}
}
