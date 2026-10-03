# LifePilot P9 — Safe Agentic Workflows

P9 introduces approval-first action execution. LifePilot may propose internal actions, but persisted or external-effect actions are not silently executed.

## Workflow

User request / grounded P8 recommendation → ActionPlan → policy validation → evidence validation → risk classification → preview → user approval → executor → verification → audit.

## Safety properties

- Every step is previewed before execution.
- All mutating steps require approval in P9; high-risk steps additionally require explicit high-risk acknowledgement.
- Financial transaction execution is blocked and out of scope.
- External integrations are represented as prepared integration requests only. P9 does not silently send email, modify an external calendar, cancel an external service, or move money.
- Assistant-origin plans require household-authorized evidence references.
- User-direct plans may be created without evidence when the action is based solely on the user's explicit instruction; the system records that no assistant evidence was asserted.
- Database-backed plans execute transactionally. If a later supported database step fails, prior database effects in the same execution attempt roll back.
- Execution idempotency prevents duplicate side effects.
- Approvals, executions, verification results and rollback results are persisted for audit.

## Supported actions

- create_task
- update_task
- create_reminder
- create_calendar_event (internal LifePilot event only)
- prepare_email_draft (draft only; never sends)
- categorize_expense
- create_expense (internal record only; never pays)
- create_asset
- create_subscription (internal record only; never signs up externally)
- archive_document (soft archive)
- request_integration_action (high-risk prepared request only)

## Routes

- GET /api/v1/action-plans
- POST /api/v1/action-plans
- GET /api/v1/action-plans/{id}
- POST /api/v1/action-plans/{id}/approve
- POST /api/v1/action-plans/{id}/execute
- POST /api/v1/action-plans/{id}/cancel
- GET /api/v1/action-plans/{id}/audit
- POST /api/v1/assistant/messages/{messageId}/action-plan

## Frontend

`/actions` opens the Action Center. The sidebar exposes it under Intelligence.
