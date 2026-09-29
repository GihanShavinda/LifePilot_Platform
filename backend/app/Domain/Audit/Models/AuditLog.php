<?php
namespace App\Domain\Audit\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { public $timestamps=false; protected $fillable=['household_id','actor_user_id','event','auditable_type','auditable_id','ip_address','user_agent','metadata','created_at']; protected function casts():array{return ['metadata'=>'array','created_at'=>'datetime'];} }
