<?php
namespace App\Domain\Documents\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateDocumentRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['title'=>['sometimes','required','string','max:255'],'category'=>['sometimes','nullable','string','exists:document_categories,slug'],'document_date'=>['sometimes','nullable','date'],'issuer'=>['sometimes','nullable','string','max:255'],'tags'=>['sometimes','array','max:20'],'tags.*'=>['string','max:80']];} }
