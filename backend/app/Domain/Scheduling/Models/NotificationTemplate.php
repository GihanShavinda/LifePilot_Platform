<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;


class NotificationTemplate extends Model {  protected $fillable=['key','locale','title_template','body_template','channels','enabled'];protected function casts():array{return ['channels'=>'array','enabled'=>'boolean'];} }
