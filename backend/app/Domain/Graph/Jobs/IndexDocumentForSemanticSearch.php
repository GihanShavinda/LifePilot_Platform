<?php
namespace App\Domain\Graph\Jobs;
use App\Domain\Documents\Models\Document;
use App\Domain\Graph\Services\DocumentIndexService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class IndexDocumentForSemanticSearch implements ShouldQueue
{
    use Queueable;
    public function __construct(public int $documentId){}
    public function handle(DocumentIndexService $index):void{if($d=Document::find($this->documentId))$index->index($d);}
}
