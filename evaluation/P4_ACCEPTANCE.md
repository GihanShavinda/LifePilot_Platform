# P4 Acceptance

- [ ] Migrations run on actual PostgreSQL; unrelated P1–P3 migrations and data remain intact.
- [ ] Manual obligation creation, invalid due date rejection and duplicate prevention.
- [ ] Unreviewed extraction fields do not yield suggestions; accepted review fields do.
- [ ] Reviewable suggestion contains document/extraction evidence; only explicit user approval creates tasks.
- [ ] Task create/read/update/delete; completion evidence and timeline.
- [ ] Checklist create/update/delete; dependency cycle and cross-household validation.
- [ ] Priority, labels, linked document, Today/Upcoming/Overdue, month calendar and drag/drop Kanban.
- [ ] Daily/weekly/monthly/yearly recurrence and generated instances; scheduler and worker run.
- [ ] Reminder recommendations, custom scheduling, snooze, delivery inbox and cancellation after completion.
- [ ] Authorization: member/owner edit, viewer read only, unrelated household denied.
- [ ] `php83 artisan test --filter=ObligationTaskTest`
- [ ] `php83 artisan test --filter=RecurrenceScheduleTest`
- [ ] `php83 artisan test` for all prior milestones.
- [ ] `npm run build` for the frontend.

The package was created from the available P2/P3 archive snapshots and validated for PHP syntax. Full Laravel integration tests and the final local Windows frontend build must still be run after merging.
