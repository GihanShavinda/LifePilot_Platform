# P11 Explainable Analytics and Lightweight Prediction

## Architecture

```text
Authorized LifePilot records
        ↓
P10 visibility filtering
        ↓
Deterministic analytics
        ↓
Data-quality / drift checks
        ↓
Interpretable prediction methods
        ↓
Evidence validation
        ↓
Persisted prediction snapshots
        ↓
Explainable insights + citations
        ↓
Analytics UI
```

## Methods

### Moving average

Recurring-expense forecasting uses the mean of the latest three monthly recurring-cost observations. Forecasts are generated separately by currency and withheld when fewer than three non-zero monthly observations are present.

### Exponential smoothing

Expected recurring spend uses simple exponential smoothing with alpha `0.35` for historical non-subscription recurring expenses. Active subscriptions are converted to a monthly equivalent using their recorded billing cycle and added separately by currency.

### Overdue risk

Task risk uses a versioned, transparent logistic-style score. Inputs are due-date proximity, priority, incomplete checklist ratio, unmet dependencies and whether work is already in progress. This is a risk indicator, not a prediction that a deadline will definitely be missed.

### Reminder recommendation

Reminder timing remains deterministic because due dates and task priority are sufficient. No statistical model is used where a rule is clearer.

## Persistence

`prediction_records` stores:

- prediction type
- method
- model version
- feature version
- input fingerprint
- prediction JSON
- confidence
- explanation
- evidence references
- generated timestamp

The input fingerprint plus version fields makes repeated prediction generation reproducible and idempotent for identical inputs.

`analytics_insights` requires non-empty evidence references. If an insight cannot be supported by concrete contributing records, P11 does not create it.

`data_quality_checks` records readiness and drift information without changing user data.

## Privacy

P11 inherits P10's explicit sharing boundary:

- Documents, tasks, expenses and assets use `accessibleTo($user)`.
- Private resources owned by another household member are not included.
- Subscriptions and obligations that do not have a P10 shared-resource contract are restricted to the current owner.
- Warranty and maintenance analytics first resolve assets visible to the current user.
- Currencies are never combined using an invented exchange rate.

## API

```text
GET  /api/v1/analytics/dashboard?months=6
POST /api/v1/analytics/refresh
GET  /api/v1/analytics/predictions
GET  /api/v1/analytics/insights
GET  /api/v1/analytics/data-quality
```

`GET /dashboard` performs read-only analytics and returns the latest persisted prediction/insight/check snapshots. `POST /refresh` explicitly recomputes and persists versioned snapshots.

## Frontend

`/analytics` includes:

- KPI summary
- spending trend bars
- subscription monthly equivalents
- evidence-cited insight cards
- prediction cards with confidence/method/version
- upcoming obligations
- warranty and maintenance schedule
- data-quality and drift status
