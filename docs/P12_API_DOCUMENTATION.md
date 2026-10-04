# LifePilot AI — API Documentation

Base URL during local development:

```text
http://localhost:8000/api/v1
```

All protected endpoints use Sanctum authentication. Browser clients should first establish the Sanctum CSRF cookie and send credentials with requests.

## P12 completion dashboards

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/completion/dashboard?months=6` | Return main, documents, finance and AI dashboard payloads together. |
| GET | `/completion/dashboard/main` | Today's tasks, upcoming obligations/payments, overdue items, subscriptions, warranties, documents, calendar and AI recommendations. |
| GET | `/completion/dashboard/documents` | Document count, processing states, categories and extraction-review queue. |
| GET | `/completion/dashboard/finance?months=6` | Monthly expenses, recurring commitments, category breakdown and subscription trend. |
| GET | `/completion/dashboard/ai` | Recommendations, predictions, evidence, pending actions and approval state. |

`months` is clamped to 3–24.

## P12 evaluation and reporting

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/evaluation/summary` | Latest run, four-variant comparison, metric catalog and security review. |
| POST | `/evaluation/run` | Persist a new evaluation snapshot. Does not fabricate metrics without evidence. |
| GET | `/evaluation/runs` | Paginated evaluation history. |
| GET | `/evaluation/security-review` | Run/persist the explicit production security checklist. |
| GET | `/evaluation/export/json` | Download final evaluation JSON. |
| GET | `/evaluation/export/csv` | Download metric table CSV. |
| GET | `/evaluation/export/pdf` | Download dependency-free PDF evaluation report. |

Example run request:

```json
{
  "label": "P12 final evaluation"
}
```

## Existing major domain endpoints

### Authentication

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`
- password reset and email verification endpoints

### Documents / intelligence

- `GET|POST /documents`
- `GET|PUT|DELETE /documents/{id}`
- `POST /documents/{id}/replace`
- `POST /documents/{id}/archive`
- `POST /documents/{id}/restore`
- `POST /documents/{id}/signed-url`
- `GET /documents/{id}/intelligence`
- `POST /documents/{id}/intelligence/reprocess`
- `PUT /documents/{documentId}/intelligence/fields/{fieldId}/review`

### Tasks / obligations

- `GET|POST /obligations`
- `POST /obligations/{id}/approve`
- `POST /obligations/{id}/dismiss`
- `GET|POST /tasks`
- `GET|PUT|DELETE /tasks/{id}`
- checklist, dependencies, reminders and timeline endpoints

### Finance / assets

- `GET /finance/dashboard`
- expense CRUD and CSV import/export
- subscription CRUD, payment confirmation and insights
- asset CRUD, warranty and maintenance endpoints

### Calendar / notifications

- calendar-event CRUD
- conflict detection
- Google Calendar connection/sync endpoints
- notification inbox/settings/push subscription endpoints

### Search / graph

- `POST /graph/sync`
- `GET /graph/entities`
- `GET /graph/entities/{id}`
- `GET /search`
- `POST /documents/{id}/semantic/reindex`

### Grounded assistant

- session CRUD/read endpoints
- `POST /assistant/sessions/{id}/messages`
- `POST /assistant/messages/{messageId}/feedback`

### Safe action planning

- action-plan list/create/show
- approve, execute, cancel and audit
- assistant-message-to-action-plan endpoint

### Household collaboration

- current household dashboard/activity
- invitation creation/accept/decline
- role management/member removal
- explicit resource-sharing scope
- task assignment and completion

### Analytics

- `GET /analytics/dashboard`
- `POST /analytics/refresh`
- `GET /analytics/predictions`
- `GET /analytics/insights`
- `GET /analytics/data-quality`

## Response convention

LifePilot uses the existing `ApiResponse` envelope for JSON endpoints:

```json
{
  "success": true,
  "data": {}
}
```

Validation errors use HTTP `422`, unauthenticated requests `401`, forbidden resource access `403/404` according to the domain boundary, and successful creations commonly use `201`.
