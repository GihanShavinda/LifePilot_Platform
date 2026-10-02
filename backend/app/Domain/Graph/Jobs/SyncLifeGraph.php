<?php
namespace App\Domain\Graph\Jobs;
use App\Domain\Graph\Services\GraphSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SyncLifeGraph implements ShouldQueue
{
    use Queueable;
    public function __construct(public int $householdId){}
    public function handle(GraphSyncService $sync):void{$sync->syncHousehold($this->householdId);}
}
