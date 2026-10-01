<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;


class BrowserPushSubscription extends Model {  protected $fillable=['user_id','endpoint','endpoint_hash','p256dh','auth_secret','last_seen_at'];protected $hidden=['auth_secret','p256dh'];protected function casts():array{return ['p256dh'=>'encrypted','auth_secret'=>'encrypted','last_seen_at'=>'datetime'];} }
