# P12 Test Report

## Scope

P12 adds tests for:

- all four final dashboard payloads
- P10 privacy isolation inside final document dashboard
- refusal to invent evaluation scores without ground truth
- labeled-case metric calculation
- AI recommendation evidence preservation
- JSON / CSV / PDF export
- manual-review status for secret-management controls
- deterministic evaluation math

## Commands

```powershell
php83 artisan test --filter=P12
php83 artisan test
```

Frontend:

```powershell
npm run build
```

## Release criteria

P12 is accepted only after the project owner runs the tests in the merged P1–P12 repository and records the actual result. This package does **not** pre-fill a pass count because the final outcome depends on the user's local merged codebase and environment.

## Expected functional assertions

1. `/completion/dashboard` returns main/documents/finance/ai sections.
2. Private records from another household member remain excluded.
3. Missing ground truth produces `not_measured`, not `0%`, `100%` or another fabricated score.
4. Four labeled `pass/pass/pass/fail` cases produce a real 75% accuracy result.
5. Evidence references survive into AI-dashboard recommendations.
6. exports return correct MIME types and the PDF starts with `%PDF-1.4`.
