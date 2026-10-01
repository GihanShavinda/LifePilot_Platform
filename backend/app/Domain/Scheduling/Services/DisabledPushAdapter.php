<?php
namespace App\Domain\Scheduling\Services;
use App\Domain\Scheduling\Contracts\PushAdapter;
use App\Domain\Scheduling\Models\Notification;
class DisabledPushAdapter implements PushAdapter {
 public function send(Notification $notification):string {throw new \RuntimeException('Push provider not configured. Notification retained for in-app display; do not report push delivery.');}
}
