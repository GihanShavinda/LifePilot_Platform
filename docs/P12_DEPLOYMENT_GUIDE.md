# LifePilot AI — Deployment Guide

## 1. Production components

- PHP 8.3+
- Laravel 12 application server
- PostgreSQL
- Redis for queue/cache
- queue worker supervisor
- scheduler
- Laravel Reverb websocket process if realtime notifications are enabled
- built React/Vite static assets
- HTTPS reverse proxy

## 2. Environment

Never copy development secrets into production.

Required areas include:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
FRONTEND_URL=https://your-domain

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=...
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=your-domain
```

Add Reverb and optional external-integration secrets only when those integrations are enabled.

## 3. Backend release

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Start long-running processes under a process manager:

```bash
php artisan queue:work --tries=3 --timeout=180
php artisan schedule:work
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## 4. Frontend release

```bash
npm ci
npm run build
```

Serve `dist/` through the production web server/CDN and configure SPA fallback to `index.html`.

## 5. Storage

- keep user document storage private
- do not map the private document disk as a public static directory
- use signed/authorized download endpoints
- back up both database and document objects consistently

## 6. Security release gate

Run:

```bash
php artisan test
npm run build
```

Then manually verify:

- authorization/BOLA
- upload allow-list and size limits
- throttling
- `APP_DEBUG=false`
- secret rotation
- no sensitive data in logs
- HTTPS and secure cookies
- queue/realtime health

## 7. P12 smoke test

After deployment:

```text
GET /api/v1/health
login through frontend
open /dashboard
open /analytics
open /evaluation
run P12 evaluation
export JSON/CSV/PDF
```

A missing labeled benchmark dataset must show `not_measured`; it must not appear as a numeric performance result.
