<?php
namespace App\Domain\Profiles\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProfileResource extends JsonResource { public static $wrap=null; public function toArray(Request $request):array{return ['id'=>$this->id,'first_name'=>$this->first_name,'last_name'=>$this->last_name,'phone'=>$this->phone,'date_of_birth'=>$this->date_of_birth?->toDateString(),'locale'=>$this->locale];} }
