<?php
namespace App\Domain\Scheduling\Contracts;
use App\Domain\Scheduling\Models\Notification;
interface PushAdapter {public function send(Notification $notification):string;}
