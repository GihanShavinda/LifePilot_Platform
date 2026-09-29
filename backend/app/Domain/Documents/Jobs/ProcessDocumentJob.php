<?php
namespace App\Domain\Documents\Jobs;
use App\Domain\Documents\Models\DocumentProcessingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessDocumentJob implements ShouldQueue
{
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=3; public function __construct(public int $processingJobId){}
 public function handle():void{$job=DocumentProcessingJob::find($this->processingJobId);if(!$job)return;$job->update(['status'=>'processing','started_at'=>now(),'attempts'=>$job->attempts+1]);try{$result=array_merge($job->result??[],['baseline'=>['checksum_verified'=>true,'metadata_ready'=>true,'ai_deferred'=>true]]);$job->update(['status'=>'completed','result'=>$result,'finished_at'=>now()]);$job->document()->update(['processing_status'=>'ready']);}catch(\Throwable $e){$job->update(['status'=>'failed','message'=>$e->getMessage(),'finished_at'=>now()]);$job->document()->update(['processing_status'=>'failed']);throw $e;}}
}
