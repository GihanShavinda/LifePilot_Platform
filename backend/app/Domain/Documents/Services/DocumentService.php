<?php
namespace App\Domain\Documents\Services;
use App\Domain\Documents\Contracts\MalwareScanner;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ProcessingStatus;
use App\Domain\Documents\Jobs\ProcessDocumentJob;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentCategory;
use App\Domain\Documents\Models\DocumentProcessingJob;
use App\Domain\Documents\Models\DocumentSource;
use App\Domain\Documents\Models\DocumentTag;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class DocumentService
{
 public function __construct(private DocumentStorageService $storage,private MalwareScanner $scanner){}
 public function upload(User $user,int $householdId,UploadedFile $file,array $data):Document{
  $this->verifyMime($file); $hash=hash_file('sha256',$file->getRealPath()); $scan=$this->scanner->scan($file->getRealPath());
  if(!$scan['clean']) throw ValidationException::withMessages(['file'=>['File rejected by malware scanner.']]);
  $stored=$this->storage->store($file,$householdId,$user->id);
  return DB::transaction(function()use($user,$householdId,$file,$data,$hash,$stored,$scan){
   $category=isset($data['category'])?DocumentCategory::where('slug',$data['category'])->first():null; $source=DocumentSource::firstOrCreate(['slug'=>$data['source']??'upload'],['name'=>'Manual Upload']);
   $doc=Document::create(['user_id'=>$user->id,'household_id'=>$householdId,'document_category_id'=>$category?->id,'document_source_id'=>$source->id,'title'=>$data['title']??pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME),'original_filename'=>$this->sanitizeOriginal($file->getClientOriginalName()),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'storage_disk'=>$stored['disk'],'storage_path'=>$stored['path'],'checksum'=>$hash,'document_date'=>$data['document_date']??null,'issuer'=>$data['issuer']??null,'status'=>DocumentStatus::Active,'processing_status'=>ProcessingStatus::Queued]);
   $version=$doc->versions()->create(['uploaded_by'=>$user->id,'version_number'=>1,'original_filename'=>$doc->original_filename,'mime_type'=>$doc->mime_type,'size'=>$doc->size,'storage_disk'=>$doc->storage_disk,'storage_path'=>$doc->storage_path,'checksum'=>$doc->checksum]);
   $this->syncTags($doc,$data['tags']??[],$householdId); $job=DocumentProcessingJob::create(['document_id'=>$doc->id,'document_version_id'=>$version->id,'job_type'=>'baseline','status'=>'queued','result'=>['malware_scan'=>$scan]]); ProcessDocumentJob::dispatch($job->id); return $doc->fresh(['category','source','tags','versions']);
  });
 }
 public function replace(Document $doc,User $user,UploadedFile $file):Document{
  $this->verifyMime($file); $hash=hash_file('sha256',$file->getRealPath()); $scan=$this->scanner->scan($file->getRealPath()); if(!$scan['clean']) throw ValidationException::withMessages(['file'=>['File rejected by malware scanner.']]); $stored=$this->storage->store($file,$doc->household_id,$user->id);
  return DB::transaction(function()use($doc,$user,$file,$hash,$stored,$scan){$next=((int)$doc->versions()->max('version_number'))+1; $version=$doc->versions()->create(['uploaded_by'=>$user->id,'version_number'=>$next,'original_filename'=>$this->sanitizeOriginal($file->getClientOriginalName()),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'storage_disk'=>$stored['disk'],'storage_path'=>$stored['path'],'checksum'=>$hash]); $doc->update(['original_filename'=>$version->original_filename,'mime_type'=>$version->mime_type,'size'=>$version->size,'storage_disk'=>$version->storage_disk,'storage_path'=>$version->storage_path,'checksum'=>$version->checksum,'processing_status'=>ProcessingStatus::Queued]); $job=DocumentProcessingJob::create(['document_id'=>$doc->id,'document_version_id'=>$version->id,'job_type'=>'baseline','status'=>'queued','result'=>['malware_scan'=>$scan]]); ProcessDocumentJob::dispatch($job->id); return $doc->fresh(['versions','category','source','tags']);});
 }
 public function syncTags(Document $doc,array $tags,int $householdId):void{$ids=collect($tags)->filter()->map(function($name)use($householdId){$name=trim((string)$name);$tag=DocumentTag::firstOrCreate(['household_id'=>$householdId,'slug'=>Str::slug($name)],['name'=>$name]);return $tag->id;})->all();$doc->tags()->sync($ids);}
 private function verifyMime(UploadedFile $file):void{$allowed=['application/pdf','image/jpeg','image/png','application/vnd.openxmlformats-officedocument.wordprocessingml.document','text/plain'];$mime=$file->getMimeType();if(!in_array($mime,$allowed,true))throw ValidationException::withMessages(['file'=>["Unsupported MIME type: {$mime}."]]);}
 private function sanitizeOriginal(string $name):string{$base=preg_replace('/[^A-Za-z0-9._ -]/','_',basename($name));return mb_substr($base?:'document',0,240);}
}
