# P6 Acceptance Checklist

- [ ] New migration ran without modifying P1–P5 data
- [ ] Calendar list/create/update/delete per-household permissions
- [ ] Timezone: 09:00 Asia/Colombo stored as 03:30 UTC
- [ ] Overlap detected with explicit confirmation before persisting
- [ ] Day/week/month frontend views and source task/document event creation
- [ ] Recurrence maintains original monthly scheduling anchor; duplicate generation idempotent
- [ ] User must accept document classification before appointment creation
- [ ] Notification prefs, quiet hours, and in-app inbox
- [ ] Delivery record, retries, errors, and unique deduplication
- [ ] No reminders for closed tasks
- [ ] P4 overdue escalation only if user enables setting
- [ ] Reverb private channel auth and realtime events
- [ ] Browser notification permission prompt originates from user click
- [ ] Google connection requires explicit OAuth and individual event export requires click
- [ ] No actual Web Push claimed with disabled adapter
- [ ] All P1–P6 tests pass and frontend production build passes locally
