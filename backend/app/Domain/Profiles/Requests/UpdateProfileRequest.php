<?php
namespace App\Domain\Profiles\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateProfileRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['first_name'=>['nullable','string','max:80'],'last_name'=>['nullable','string','max:80'],'phone'=>['nullable','string','max:30'],'date_of_birth'=>['nullable','date','before:today'],'locale'=>['nullable','string','max:12'],'timezone'=>['sometimes','timezone:all']];} }
