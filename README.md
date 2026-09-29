# LifePilot AI — P2 Document Management Foundation

This repository contains the P1 authentication foundation plus P2 document management. AI/OCR is intentionally deferred to P3.

## Backend
```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work
php artisan serve --host=localhost --port=8000
```

Use PHP 8.3+ for this project. If your machine keeps PHP 8.2 as the default, invoke Composer and Artisan with your PHP 8.3 executable/alias.

For the existing Windows setup used during P1, PostgreSQL may be on port `5433`, Redis may be mapped to host port `6382`, and the React dev server is configured by your local frontend environment. Keep the ports that are already working on your machine.

## Frontend
```powershell
cd frontend
npm install
Copy-Item .env.example .env
npm run dev
```

Set `VITE_API_URL=http://localhost:8000` and ensure Laravel CORS/Sanctum includes the actual Vite origin.

## P2 storage
Development uses the private local `documents` disk. For MinIO/S3, set `DOCUMENTS_DRIVER=s3`, bucket credentials, endpoint, and path-style endpoint configuration as appropriate.

## Verification
```powershell
php artisan migrate --seed
php artisan test
php artisan route:list --path=api/v1/documents
npm run build
```

See `docs/P2_DOCUMENTS.md` and `evaluation/P2_ACCEPTANCE.md`.
