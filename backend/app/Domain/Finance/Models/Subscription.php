<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Subscription extends Model {
 use SoftDeletes;
 protected $table='subscriptions';
 protected $fillable=['household_id', 'user_id', 'document_id', 'merchant_id', 'name', 'provider', 'price', 'currency', 'billing_cycle', 'next_billing_date', 'renewal_type', 'cancellation_deadline', 'status', 'previous_price', 'last_price_change_at', 'dedupe_key', 'last_generated_billing_date','reminder_task_id'];
 protected function casts():array { return ['price'=>'decimal:2', 'previous_price'=>'decimal:2', 'next_billing_date'=>'date', 'cancellation_deadline'=>'date', 'last_generated_billing_date'=>'date', 'last_price_change_at'=>'datetime','cancellation_deadline'=>'date']; }
 public function payments(){return $this->hasMany(SubscriptionPayment::class);} public function merchant(){return $this->belongsTo(Merchant::class);} public function document(){return $this->belongsTo(\App\Domain\Documents\Models\Document::class);}
}
