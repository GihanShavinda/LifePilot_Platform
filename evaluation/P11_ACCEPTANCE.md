# P11 Acceptance Checklist

- [ ] Migration `2026_10_04_001100_create_analytics_prediction_tables` ran.
- [ ] `prediction_records`, `analytics_insights`, and `data_quality_checks` exist.
- [ ] `/api/v1/analytics/dashboard` is protected by Sanctum.
- [ ] `/api/v1/analytics/refresh` persists all five prediction types.
- [ ] Recurring-expense forecast with insufficient history returns `insufficient_history` instead of fabricated values.
- [ ] Identical inputs and versions produce the same prediction fingerprint and prediction payload.
- [ ] Prediction records contain model version, feature version, confidence, explanation and generated time.
- [ ] Next subscription charge is deterministic from stored subscription data.
- [ ] Overdue-risk prediction explains its features and cites task records.
- [ ] Expected recurring spend keeps currencies separate.
- [ ] Reminder timing is a recommendation and does not create reminders.
- [ ] Insights contain non-empty evidence references.
- [ ] Month-to-month insight is not generated when there is no valid previous-month baseline.
- [ ] P10-private expenses belonging to another member are excluded.
- [ ] Data-quality checks identify insufficient history and large spending drift.
- [ ] Frontend `/analytics` builds and loads.
- [ ] `php83 artisan test --filter=P11` passes.
- [ ] Full regression suite passes.
