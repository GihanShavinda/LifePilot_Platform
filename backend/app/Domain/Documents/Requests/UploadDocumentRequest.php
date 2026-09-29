<?php
namespace App\Domain\Documents\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UploadDocumentRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['file'=>['required','file','max:'.config('documents.max_upload_kb',15360),'mimes:pdf,jpg,jpeg,png,docx,txt'],'title'=>['nullable','string','max:255'],'category'=>['nullable','string','exists:document_categories,slug'],'source'=>['nullable','string','max:80'],'document_date'=>['nullable','date'],'issuer'=>['nullable','string','max:255'],'tags'=>['nullable','array','max:20'],'tags.*'=>['string','max:80']];} }
