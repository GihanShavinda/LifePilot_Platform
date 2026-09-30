<?php

// Run with php83 artisan schedule:work (development), or cron in production.
\Illuminate\Support\Facades\Schedule::job(new \App\Domain\Obligations\Jobs\GenerateRecurringTasks)->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::job(new \App\Domain\Obligations\Jobs\DispatchDueReminders)->everyMinute()->withoutOverlapping();
