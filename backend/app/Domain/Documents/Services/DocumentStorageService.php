<?php
namespace App\Domain\Documents\Services;
use App\Domain\Documents\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
class DocumentStorageService
{
 public function disk():string{return config('documents.disk','documents');}
 public function store(UploadedFile $file,int $householdId,int $userId):array{
  $safeBase=Str::slug(pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME)) ?: 'document'; $ext=strtolower($file->getClientOriginalExtension());
  $name=$safeBase.'-'.Str::uuid().($ext?'.'.$ext:''); $path="households/{$householdId}/users/{$userId}/documents/{$name}";
  Storage::disk($this->disk())->putFileAs(dirname($path),$file,basename($path),'private');
  return ['disk'=>$this->disk(),'path'=>$path,'filename'=>$name];
 }
 public function delete(string $disk,string $path):void{Storage::disk($disk)->delete($path);}
 public function signedUrl(DocumentVersion $version,int $minutes=10):string{
  try{return Storage::disk($version->storage_disk)->temporaryUrl($version->storage_path,now()->addMinutes($minutes));}
  catch(\Throwable){return URL::temporarySignedRoute('documents.signed-download',now()->addMinutes($minutes),['document'=>$version->document_id,'version'=>$version->id]);}
 }
}
