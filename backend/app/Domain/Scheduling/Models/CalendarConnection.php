<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;


class CalendarConnection extends Model {  protected $fillable=['user_id','household_id','provider','external_calendar_id','access_token','refresh_token','token_expires_at','scopes','status','authorized_at'];protected $hidden=['access_token','refresh_token'];protected function casts():array{return ['access_token'=>'encrypted','refresh_token'=>'encrypted','scopes'=>'array','token_expires_at'=>'datetime','authorized_at'=>'datetime'];} }
