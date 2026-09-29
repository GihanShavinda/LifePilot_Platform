# P1 Architecture

## Boundary

P1 establishes identity, household tenancy, preferences, auditing and frontend authentication. AI capabilities are intentionally absent.

## Backend layering

`app/Domain/*` contains domain-specific models, enums, controllers, requests, resources and services. API routes are versioned beneath `/api/v1`.

## Household authorization

A `HouseholdMember` joins users to households and stores one of three roles:

- `owner`
- `family_member`
- `viewer`

`RequireHouseholdRole` checks the authenticated user's membership in the current household before protected operations.

## Audit design

`AuditService` writes immutable append-only records containing actor, household, event name, auditable entity, IP address, user agent and structured metadata. Sensitive payloads such as passwords are never stored.

## Authentication security

- Laravel Sanctum stateful SPA authentication
- CSRF cookie initialization before state-changing auth requests
- Session regeneration after successful login/registration
- Session invalidation + CSRF token rotation on logout
- Login/register/forgot-password rate limits
- No frontend localStorage bearer token

## Error envelope

Errors use:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {}
  }
}
```

Successful responses use:

```json
{
  "success": true,
  "data": {}
}
```
