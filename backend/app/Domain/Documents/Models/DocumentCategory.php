<?php
namespace App\Domain\Documents\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentCategory extends Model { protected $fillable=['slug','name','is_system']; protected function casts():array{return ['is_system'=>'boolean'];} }
