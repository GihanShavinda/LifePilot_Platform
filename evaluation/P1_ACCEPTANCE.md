# P1 Acceptance Checklist

- [x] Laravel 12 API source scaffold
- [x] `/api/v1` route versioning
- [x] Sanctum SPA/session auth architecture
- [x] Register/login/logout/me
- [x] Email verification endpoints
- [x] Password reset endpoints
- [x] User/Profile/Household/HouseholdMember models
- [x] AuditLog model and audit service
- [x] NotificationPreference model
- [x] IntegrationConnection model
- [x] Owner / Family Member / Viewer roles
- [x] Profile CRUD
- [x] Timezone preference
- [x] Notification preferences
- [x] Validation request classes
- [x] API resource classes
- [x] Standard error envelopes
- [x] Pagination helper
- [x] Rate limiting
- [x] Protected frontend routes
- [x] CSRF-aware browser session handling
- [x] Requested feature-test coverage source
- [x] AI service contains health scaffolding only

## Local verification commands

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test

cd ../frontend
npm install
npm run build

cd ../ai-service
pip install -r requirements.txt
python -m pytest
```
