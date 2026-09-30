<?php
namespace App\Domain\Finance\Models;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model {

 protected $table='maintenance_records';
 protected $fillable=['household_id', 'asset_id', 'title', 'notes', 'performed_at', 'next_due_date', 'cost', 'currency', 'document_id', 'reminder_task_id'];
 protected function casts():array { return ['performed_at'=>'date', 'next_due_date'=>'date', 'cost'=>'decimal:2']; }
 public function asset(){return $this->belongsTo(Asset::class);} public function document(){return $this->belongsTo(\App\Domain\Documents\Models\Document::class);}
}
