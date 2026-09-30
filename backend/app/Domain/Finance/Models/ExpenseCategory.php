<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model {

 protected $table='expense_categories';
 protected $fillable=['household_id', 'name', 'slug', 'monthly_budget'];
 protected function casts():array { return ['monthly_budget'=>'decimal:2']; }
 public function expenses(){return $this->hasMany(Expense::class);}
}
