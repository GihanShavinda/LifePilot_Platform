# LifePilot AI — Milestone 1 (P1)

P1 establishes the non-AI platform foundation for LifePilot AI.

## Included

- Laravel 12 REST API under `/api/v1`
- React + TypeScript + Vite frontend
- PostgreSQL and Redis configuration
- Laravel Sanctum SPA/session authentication
- Registration, login, logout, email verification, password reset
- User profile CRUD
- Timezone and notification preferences
- Household membership roles: Owner, Family Member, Viewer
- Audit logs
- Standard API success/error envelopes
- Validation, API resources, rate limiting, pagination helper
- Protected frontend routes and CSRF-aware API client
- PHPUnit feature tests for requested P1 scenarios
- FastAPI placeholder service with health endpoint only (no AI)

## Repository

```text
backend/      Laravel 12 API
frontend/     React + TypeScript UI
ai-service/   FastAPI placeholder only
/docs         Architecture and API notes
/evaluation   P1 acceptance checklist
```

## 1. Backend setup

Requirements: PHP 8.3+, Composer, PostgreSQL, Redis.

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Optional queue worker:

```bash
php artisan queue:work
```

Run tests:

```bash
php artisan test
```

Default API base: `http://127.0.0.1:8000/api/v1`

For a local SPA, keep `FRONTEND_URL=http://localhost:5173` and add the frontend host to `SANCTUM_STATEFUL_DOMAINS`.

## 2. Frontend setup

Requirements: Node.js 20.19+ recommended.

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Frontend: `http://localhost:5173`

## 3. AI placeholder service

P1 deliberately contains no AI implementation.

```bash
cd ai-service
python -m venv .venv
# Windows: .venv\\Scripts\\activate
# Linux/macOS: source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8100
```

Health: `GET http://127.0.0.1:8100/health`

## Authentication flow

1. React requests `GET /sanctum/csrf-cookie`.
2. React submits register/login with `credentials: include`.
3. Laravel authenticates via secure session cookie.
4. Protected API requests continue with session credentials.
5. Logout invalidates the session and rotates the CSRF token.

The React app does **not** persist auth tokens in localStorage/sessionStorage.

## Main API routes

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/email/verification-notification`
- `GET /api/v1/auth/email/verify/{id}/{hash}`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `GET|PUT|DELETE /api/v1/profile`
- `GET|PUT /api/v1/notification-preferences`
- `GET /api/v1/households/current`
- `GET /api/v1/households/current/members`
- `GET /api/v1/audit-logs`
- `GET /api/v1/owner-check` (Owner-only RBAC test/demo route)

## Notes

- Email verification/password reset emails use Laravel notification mechanisms. Configure `MAIL_*` for real delivery.
- Redis is configured for cache/queues; tests can use array/sync drivers.
- Integration connections exist as a model/schema only in P1; OAuth integrations are intentionally deferred.
- No external action execution and no AI decisioning is implemented in this milestone.
