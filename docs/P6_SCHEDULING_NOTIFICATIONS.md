# LifePilot AI P6 — Scheduling and Notifications

## Scope and consent

- Internal calendar CRUD, explicit task/document-to-event action, day/week/month UI, source links, conflict confirmation, UTC persistence and IANA time zones.
- Recurring event instances are generated for the next 45 days by queued jobs. Later occurrences appear as the generation horizon advances. Changing a recurrence rule requires replacing its series; deleting an individual occurrence does not remove the whole series.
- In-app notifications, optional email via Laravel Mail, foreground browser notifications through Reverb, queued status/attempt logging, retry and quiet-hour scheduling. Push is an interface/boundary: **no actual remote Web Push delivery without configuring a real adapter**.
- Google Calendar OAuth connection stores encrypted credentials. The user must explicitly initiate Connect and explicitly choose Export on an individual event. Scheduled jobs NEVER export or modify Google Calendar.

## Setup (Windows PowerShell)

```powershell
cd 'F:\My_Projects\Lifepilot_Platform\lifepilot\backend'
Set-Alias php83 'C:\php-8.3.35-nts-Win32-vs16-x64\php.exe'
docker start lifepilot_redis
php83 'C:\ProgramData\ComposerSetup\bin\composer.phar' update laravel/reverb --with-all-dependencies
php83 artisan migrate
php83 artisan db:seed --class=P6NotificationTemplateSeeder
php83 artisan optimize:clear
php83 artisan route:list --path=api/v1/calendar
php83 artisan route:list --path=api/v1/notifications
php83 artisan schedule:list
php83 artisan test --filter=P6
php83 artisan test
```

Configure `backend/.env` using values from `backend/.env.example`, retaining your current DB 5433 and Redis 6382 settings. **Use unique, matching Reverb keys and secret; never commit `.env`.** Configure MAIL_MAILER (e.g., log during development); `MAIL_MAILER=log` means emails are not actually delivered to mailboxes.

In frontend, merge `package.json`, then run `npm install`. Configure `frontend/.env` using `frontend/.env.example`, and retain `VITE_API_URL=http://localhost:8000`.

### Six terminals

1. API: `php83 artisan serve --host=localhost --port=8000`
2. Queue: `php83 artisan queue:work --tries=3 --timeout=180 -v` (set your Poppler/Tesseract PATH as in P3)
3. Scheduler: `php83 artisan schedule:work`
4. Reverb: `php83 artisan reverb:start --host=0.0.0.0 --port=8080`
5. Frontend: `npm run dev`
6. Debug/test: `php83 artisan test --filter=P6`; `php83 artisan queue:failed`; `Get-Content .\storage\logs\laravel.log -Tail 100 -Wait`

Keep Reverb listening only on trusted developer networks; use HTTPS/WSS and secure origin settings before deployment. The websocket auth endpoint is scoped to each authenticated user's private channel.

## Manual acceptance

1. Login and open `/calendar`; create event; inspect day/week/month views and overlap confirmation.
2. Create a task-linked event via `/calendar?task_id=<owned-task-id>` and an appointment from an accepted document via `/calendar?document_id=<owned-doc-id>`; user must specify the event time explicitly.
3. Add recurring monthly event starting at month end, then check multiple generated occurrences after queue runs. Check `php83 artisan schedule:list`.
4. Open `/notifications`; configure Asia/Colombo quiet hours spanning midnight; enable browser notifications with explicit permission. Trigger a due P4 reminder and verify notification row/delivery status. `NotificationDelivery` is the audit trail for channel attempts. In-app messages bypass quiet hours; email/browser follow them.
5. Check completion of tasks cancels outstanding reminders, and P6 delivery refuses completed-task notifications. Confirm duplicate reminder scheduling creates a single notification via unique key.
6. Set real `MAIL_MAILER`/SMTP credentials to test email delivery; test with log mailer first and inspect logs. Remote push remains disabled until an implementation of `PushAdapter` is bound in the service provider, credentials are configured, and a user explicitly registers a browser push subscription. Merely setting `LIFEPILOT_PUSH_PROVIDER` does not install a delivery provider.
7. For Google, register a Google Cloud OAuth client, set localhost callback URI to exactly `http://localhost:8000/api/v1/calendar/google/callback`, connect intentionally, select one local event, and explicitly Export. No background bidirectional sync or deletion is performed. Disconnect clears stored credentials (Google token revocation outside this app may also be required).
8. Verify cross-household access is denied, viewer cannot edit, and websocket channels only authenticate for own `user.{id}`.

## Limitations and follow-on hardening

- P6 frontend browser notifications are foreground alerts; **background push requires a separately implemented push transport**. The disabled adapter reports errors rather than claiming delivery.
- Use a dedicated mail transport and queue metrics in production. Review and reconcile provider-side ambiguous failures (e.g., a timeout after provider accepted an email) before attempting exactly-once delivery claims. Idempotency is enforced at notification scheduling and per-channel row levels, but email providers cannot guarantee exactly-once delivery.
- No imported external calendars or automatic Google synchronization in P6. Only an explicit user-approved export of one event is included.
- Cancellation of an exported local event does not delete its Google counterpart automatically; surface the mismatch to the user.
