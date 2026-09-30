<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class Warranty extends Model {

 protected $table='warranties';
 protected $fillable=['household_id', 'asset_id', 'provider', 'start_date', 'end_date', 'coverage_notes', 'proof_document_id', 'status', 'reminder_task_id'];
 protected function casts():array { return ['start_date'=>'date', 'end_date'=>'date']; }
 public function asset(){return $this->belongsTo(Asset::class);} public function proofDocument(){return $this->belongsTo(\App\Domain\Documents\Models\Document::class,'proof_document_id');} public function reminderTask(){return $this->belongsTo(\App\Domain\Obligations\Models\Task::class,'reminder_task_id');}
}
