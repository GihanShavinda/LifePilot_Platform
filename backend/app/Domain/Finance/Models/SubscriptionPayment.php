<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model {

 protected $table='subscription_payments';
 protected $fillable=['subscription_id', 'expense_id', 'amount', 'currency', 'billing_date', 'source'];
 protected function casts():array { return ['amount'=>'decimal:2', 'billing_date'=>'date']; }
 public function subscription(){return $this->belongsTo(Subscription::class);} public function expense(){return $this->belongsTo(Expense::class);}
}
