<?php
namespace App\Domain\Scheduling\Events;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class LifePilotUpdated implements ShouldBroadcast {
 use Dispatchable,InteractsWithSockets,SerializesModels;
 public bool $afterCommit=true;
 public function __construct(public int $userId,public string $kind,public array $data=[]){}
 public function broadcastOn():array{return [new PrivateChannel('user.'.$this->userId)];}
 public function broadcastAs():string{return 'lifepilot.updated';}
 public function broadcastWith():array{return ['kind'=>$this->kind,'data'=>$this->data];}
}
