<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Expense extends Model {
 use SoftDeletes;
 protected $table='expenses';
 protected $fillable=['household_id', 'user_id', 'expense_category_id', 'merchant_id', 'document_id', 'receipt_extraction_id', 'subscription_id', 'title', 'description', 'amount', 'currency', 'expense_date', 'source', 'import_hash', 'recurrence_cycle', 'next_occurrence_date'];
 protected function casts():array { return ['amount'=>'decimal:2', 'expense_date'=>'date', 'next_occurrence_date'=>'date']; }
 public function category(){return $this->belongsTo(ExpenseCategory::class,'expense_category_id');} public function merchant(){return $this->belongsTo(Merchant::class);} public function document(){return $this->belongsTo(\App\Domain\Documents\Models\Document::class);} public function subscription(){return $this->belongsTo(Subscription::class);}
}
