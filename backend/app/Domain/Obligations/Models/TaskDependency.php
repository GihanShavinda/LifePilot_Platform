<?php
namespace App\Domain\Obligations\Models;
use Illuminate\Database\Eloquent\Model;
class TaskDependency extends Model {protected $fillable=['task_id','depends_on_task_id'];}
