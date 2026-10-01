<?php

namespace Database\Seeders;

use App\Domain\Scheduling\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class P6NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                ['key' => 'reminder', 'title_template' => 'Task reminder: {title}', 'body_template' => 'Review this task before its due date.', 'channels' => ['in_app', 'email', 'browser']],
                ['key' => 'calendar_reminder', 'title_template' => 'Upcoming event: {title}', 'body_template' => 'Your scheduled calendar event is approaching.', 'channels' => ['in_app', 'email', 'browser']],
                ['key' => 'task_escalation', 'title_template' => 'Important overdue task: {title}', 'body_template' => 'This task is overdue and requires attention.', 'channels' => ['in_app', 'email', 'browser']],
            ] as $value
        ) {
            NotificationTemplate::updateOrCreate(['key' => $value['key']], $value + ['locale' => 'en', 'enabled' => true]);
        }
    }
}
