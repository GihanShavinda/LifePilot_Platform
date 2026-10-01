<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', fn($user, int $userId) => (int)$user->id === $userId);
