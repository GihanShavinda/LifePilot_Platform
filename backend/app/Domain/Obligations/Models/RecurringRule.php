<?php
namespace App\Domain\Obligations\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class RecurringRule extends Model {
 protected $fillable=['household_id','frequency','interval','starts_at','until_at','max_occurrences','generated_occurrences','next_at','enabled'];
 protected function casts():array{return ['starts_at'=>'datetime','until_at'=>'datetime','next_at'=>'datetime','enabled'=>'boolean'];}
 public function tasks():HasMany{return $this->hasMany(Task::class);}
}
