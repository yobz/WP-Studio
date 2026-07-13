# WP Studio API

Laravel 12 backend for [WP Studio](../README.md). Serves the
versioned REST API the Next.js frontend consumes — see
[`docs/adr/0004-backend-foundation.md`](../docs/adr/0004-backend-foundation.md)
for the full architecture and contract.

## Requirements

- PHP 8.2+ with the `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`,
  `xml`, `ctype`, `fileinfo`, `bcmath`, and `curl` extensions (all
  standard in most PHP distributions, including XAMPP's bundled PHP).
- [Composer](https://getcomposer.org) 2.x.

No local MySQL/Postgres server, Docker, or Node toolchain is required
to run the backend — local development uses SQLite (a single file,
zero setup).

## Local setup

```bash
cd backend
composer install
cp .env.example .env      # skip if .env already exists
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

Verify it's working:

```bash
curl http://localhost:8000/api/v1/health
# {"success":true,"data":{"status":"ok","checks":{"database":{"status":"ok"}}}}
```

## Environment configuration

All configuration lives in `.env` (copied from `.env.example`, never
committed). The variables specific to this project, beyond Laravel's
own defaults:

| Variable | Purpose | Local default |
| --- | --- | --- |
| `APP_URL` | This API's own base URL | `http://localhost:8000` |
| `FRONTEND_URLS` | Comma-separated origins allowed to make CORS requests (see `config/cors.php`) | `http://localhost:3000` |
| `DB_CONNECTION` | Database driver | `sqlite` |
| `SENTRY_LARAVEL_DSN` (commented out) | Future error-reporting integration — not implemented yet | — |
| `OTEL_EXPORTER_OTLP_ENDPOINT` (commented out) | Future tracing integration — not implemented yet | — |

The frontend's own `.env.local` (repo root) needs `NEXT_PUBLIC_API_URL`
pointing at this API's URL — already set to `http://localhost:8000` by
default in `.env.example` at the repo root.

## Running both apps together

Two terminals, both from the repo root:

```bash
npm run dev                       # Next.js — http://localhost:3000
(cd backend && php artisan serve) # Laravel — http://localhost:8000
```

## Testing

[Pest](https://pestphp.com) — see `tests/Feature/DashboardSummaryTest.php`
for the pattern this project follows for API endpoint tests.

```bash
php artisan test
```

## Useful commands

```bash
php artisan route:list --path=api   # every registered API route
php artisan migrate:fresh --seed    # reset the local database
php artisan tinker                  # REPL against the app
```

## Architecture

See [`docs/adr/0004-backend-foundation.md`](../docs/adr/0004-backend-foundation.md)
for directory structure, the API response envelope, versioning
strategy, the mock-to-real migration approach, and every trade-off
made building this foundation.
