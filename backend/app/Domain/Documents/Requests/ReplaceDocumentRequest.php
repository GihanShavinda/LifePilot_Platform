<?php
namespace App\Domain\Documents\Requests;
use Illuminate\Foundation\Http\FormRequest;
class ReplaceDocumentRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['file'=>['required','file','max:'.config('documents.max_upload_kb',15360),'mimes:pdf,jpg,jpeg,png,docx,txt']];} }
