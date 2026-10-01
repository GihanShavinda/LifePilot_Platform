<?php
namespace App\Domain\Scheduling\Contracts;
use App\Domain\Scheduling\Models\{CalendarConnection,CalendarEvent};
interface CalendarProvider {public function authorizationUrl(string $state):string;public function exchangeCode(string $code):array;public function createEvent(CalendarConnection $connection,CalendarEvent $event):string;}
