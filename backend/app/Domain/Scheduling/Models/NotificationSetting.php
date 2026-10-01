<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;


class NotificationSetting extends Model {  protected $fillable=['user_id','quiet_start','quiet_end','timezone','browser_enabled','in_app_enabled','escalation_enabled'];protected function casts():array{return ['browser_enabled'=>'boolean','in_app_enabled'=>'boolean','escalation_enabled'=>'boolean'];} }
