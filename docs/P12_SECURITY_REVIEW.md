# LifePilot AI — Security Review

## OWASP API risks

LifePilot applies authentication, authorization, input validation, resource scoping, throttling and auditable mutation flows. P12 does not claim a penetration test has been completed; production deployment should still undergo independent security testing.

## Authorization / BOLA

P10 enforces private-by-default access. Same-household membership is insufficient to access another user's document, task, expense, asset or calendar event unless it has explicit household sharing scope. The boundary is reused by search, grounded retrieval and agentic execution.

Recommended release checks:

```powershell
php83 artisan test --filter=RbacTest
php83 artisan test --filter=P10
php83 artisan test --filter=P8
php83 artisan test --filter=P9
```

## File upload security

Current upload request validation uses an allow-list of `pdf,jpg,jpeg,png,docx,txt` and a configured maximum size. Files are not exposed through public storage paths; authorized signed download URLs are used. Production should additionally use malware scanning/content disarm where the threat model requires it.

## Prompt injection

Document content is untrusted. P8 wraps retrieval with prompt-injection defenses and validates claims/citations/factual values before accepting model output. Unsupported amounts and deadlines trigger deterministic fallback.

## Rate limiting

Rate limits exist on authentication, document upload, extraction reprocessing, assistant asking, P9 action execution, P10 invitation creation and P11 analytics refresh. Production limits should be tuned to deployment capacity and abuse telemetry.

## Sensitive logging

Manual production check required:

- `APP_DEBUG=false`
- avoid logging document text, passwords, tokens or personal financial values
- redact authorization/cookie headers in reverse-proxy and application logs
- configure retention and access controls

## Secret management

Manual production check required:

- never commit `.env`
- rotate development credentials before deployment
- use a platform secret manager for database, Reverb and integration secrets
- restrict CI/CD secret visibility

## Agentic safety

P9 requires plan preview, evidence validation, policy validation, risk classification and approval before execution. High-risk requests require explicit acknowledgement. Financial transaction execution remains out of scope.
