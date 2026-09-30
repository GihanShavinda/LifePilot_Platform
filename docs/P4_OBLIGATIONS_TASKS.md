# LifePilot P4 — Obligations and tasks

Merge this changes-only ZIP into your **working P3 project**; it is not a standalone replacement. Retain your local `.env`, updated P3 deterministic parser and the P2 pivot timestamp migration you added locally. Back up before merging.

## What P4 implements

- Manual obligations, household-scoped duplicate fingerprints and validation of new due dates.
- **Read-only obligation suggestions** based only on accepted/edited P3 extraction fields. Suggestions never write tasks. `POST .../approve` is the explicit action that persists and approves a suggestion and creates its task.
- Household-member write access (owner / family_member), viewer read-only, document ownership enforced through household boundary.
- Task CRUD, priorities, labels, checklist items, cross-household-resistant dependencies with cycle checks, completion evidence, linked documents and activity timeline.
- Today / Upcoming / Overdue / all views, month-grid Calendar and drag/drop Kanban UI. Overdue is a derived effective state: a pending or in-progress task past due appears overdue without rewriting historical status.
- Recurrence: daily / weekly / monthly / yearly with interval, optional end date / occurrence cap, scheduler-generated child occurrences. Each instance has its own reminders and completion status. Month-end uses non-overflow arithmetic.
- Smart **deterministic** reminder recommendations: seven days, two days and due-day when sufficient time remains. Reminders have manual scheduling and snooze, and completed or skipped tasks have outstanding reminders cancelled. In-app reminders are delivered as `sent` records via schedule + queue and shown in the Tasks UI; SMTP/mobile push is **not** implemented.

## Important security notes

- Do not promote arbitrary raw OCR or unreviewed LLM guesses to obligations: both the type/date candidates needed for an obligation must be accepted/edited. Human approval of suggestions is mandatory.
- Approving a historical obligation with a due date that has already passed is rejected. Use a corrected current due date via a manual obligation.
- Duplication is guarded by application fingerprint check **and** a database unique constraint. If two requests race, the DB will reject one; the API may need a conflict-specific error mapping in high-concurrency production deployments.
- The first current household membership is used, matching the existing P2 single-active-household convention. For true multi-household switching, add explicit active household selection throughout P1–P4.
- Extraction evidence is preserved for review but is not proof of authenticity of the underlying document.

## Setup on your Windows machine

```powershell
cd "F:\My_Projects\Lifepilot_Platform\lifepilot\backend"
Set-Alias php83 "C:\php-8.3.35-nts-Win32-vs16-x64\php.exe"
docker start lifepilot_redis
php83 artisan migrate
php83 artisan optimize:clear
php83 artisan test --filter=ObligationTaskTest
php83 artisan test --filter=RecurrenceScheduleTest
php83 artisan test
```

Open four terminals:

```powershell
# Terminal 1: Laravel API
php83 artisan serve --host=localhost --port=8000

# Terminal 2: job worker
php83 artisan queue:work --tries=3 --timeout=180

# Terminal 3: Laravel scheduler (required for recurrence + due reminders)
php83 artisan schedule:work

# Terminal 4, from frontend/
npm install
npm run dev
npm run build  # separate check, not a long-running process
```

Keep the P3 text extraction executables in the queue worker PATH if you will process documents.

## Test workflow

1. Login; visit `http://localhost:5174/tasks`.
2. Create a manual payment obligation with a future due date. Check that no task exists yet.
3. Explicitly approve it. Confirm exactly one task and its recommended reminders.
4. In Documents, finish reviewing the electricity bill's **actual** extracted type and due date, correcting and accepting values as appropriate. Open its details and click *Review obligation suggestions*.
5. Inspect the source evidence and click the explicit approval button. Reload Tasks: the generated task must link to the original document.
6. Repeat the same approval: the earlier obligation's fingerprint blocks duplicates.
7. Create two manual tasks; add a dependency and verify a cyclic dependency is rejected. Complete prerequisites before the dependent task.
8. Add/complete checklist items, change priority/labels, complete with evidence and verify activity history.
9. Test a recurring task with a near-future due date; the scheduler creates subsequent occurrences at the defined time. Do not modify your actual clock; use automated tests for recurrence and overdue behavior.
10. Snooze a reminder before its due date, then complete the task and verify pending reminders are cancelled.
11. Verify another household cannot fetch or modify the first household's tasks and documents.

### Key endpoints

- `GET/POST /api/v1/obligations`; `POST /api/v1/obligations/{id}/approve`; `POST .../dismiss`
- `GET /api/v1/documents/{id}/obligation-suggestions`; `POST .../obligation-suggestions/approve`
- `GET/POST /api/v1/tasks`; `GET/PUT/DELETE /api/v1/tasks/{id}`; task checklist, dependencies, reminders, timeline routes (see `php83 artisan route:list --path=api/v1`).
- `GET /api/v1/task-reminders` for in-app reminder inbox.

P4 does **not** auto-create tasks on P3 document upload/reprocessing. That remains a deliberate, safe approval workflow.
