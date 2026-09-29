<?php
namespace App\Domain\Integrations\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class IntegrationConnection extends Model { protected $fillable=['user_id','provider','provider_account_id','status','scopes','connected_at','last_synced_at']; protected $hidden=['encrypted_credentials']; protected function casts():array{return ['scopes'=>'array','connected_at'=>'datetime','last_synced_at'=>'datetime','encrypted_credentials'=>'encrypted:array'];} public function user():BelongsTo{return $this->belongsTo(\App\Domain\Users\Models\User::class);} }
