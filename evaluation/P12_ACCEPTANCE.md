# P12 Acceptance Checklist

- [ ] Migration `2026_10_04_001200_create_final_evaluation_tables` ran.
- [ ] `evaluation_runs`, `evaluation_cases`, `evaluation_metric_results`, `security_review_results` exist.
- [ ] Main dashboard contains today's tasks, obligations, overdue items, payments, subscriptions, warranties, recent documents, calendar and AI recommendations.
- [ ] Document dashboard contains totals, processing state, categories and extraction-review queue.
- [ ] Finance dashboard contains monthly expenses, recurring commitments, categories and subscription trend.
- [ ] AI dashboard contains recommendations, predictions/confidence/evidence, pending actions and approval state.
- [ ] P10 private-resource boundaries remain enforced in P12 dashboards.
- [ ] All 15 requested evaluation metrics appear in the catalog.
- [ ] Metrics without ground truth are `not_measured`.
- [ ] Four system variants are compared without invented scores.
- [ ] Security review covers OWASP API authorization, upload security, prompt injection, BOLA, rate limiting, logging and secrets.
- [ ] PDF, CSV and JSON exports work.
- [ ] Final architecture, ERD, API docs, test report, evaluation table, deployment guide, README and portfolio description exist.
- [ ] `php83 artisan test --filter=P12` passes.
- [ ] Full backend regression passes.
- [ ] `npm run build` passes.
