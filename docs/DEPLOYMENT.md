# Deployment Runbook

How to actually take WP Studio live: Vercel (frontend) + Railway
(backend + Postgres), the target this project has named since
`docs/PROJECT.md`'s Stack table. Everything in this document is
configuration and account setup, not code — Milestone 19
(`docs/adr/0017-cloud-deployment-and-security-hardening.md`) made the
codebase ready for every step here; none of it requires touching the
application itself. This runbook is what a real deploy needs beyond
that.

**Scope note.** This project's own Milestone 19 stopped at
"deployment-ready," not "deployed" — provisioning real Vercel/Railway
accounts, a real domain, and real object storage credentials requires
account access this project's automated milestone work doesn't have.
This document is what a human operator follows to actually go live.

---

## 1. Prerequisites

- A Vercel account and the [Vercel CLI](https://vercel.com/docs/cli) (or just the dashboard).
- A Railway account and the [Railway CLI](https://docs.railway.app/guides/cli) (or just the dashboard).
- A registered domain you control DNS for (needed for the cross-domain
  auth strategy in §4 — skip it and Sanctum's cookie auth won't work
  across two unrelated `*.vercel.app`/`*.up.railway.app` domains).
- An object storage bucket — Cloudflare R2 recommended (§3), any
  S3-compatible provider works.
- Optional: a [Sentry](https://sentry.io) project (free tier) for a
  real DSN — `docs/adr/0016-observability.md`'s integration is
  code-complete and DSN-optional; this is the step that makes it live.

---

## 2. Database: PostgreSQL on Railway

`docs/adr/0017-cloud-deployment-and-security-hardening.md` chose
PostgreSQL over MySQL and verified it directly: every migration and
all 145 backend tests pass against a real Postgres 16 instance with
zero code changes. Railway's own Postgres add-on is the simplest path
— provision it from the Railway project dashboard ("+ New" → "Database"
→ "PostgreSQL"), then set the backend service's environment from the
values Railway generates:

```
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}
```

(Railway's `${{ServiceName.VAR}}` reference syntax wires one service's
generated credentials into another's environment — no copy-pasting a
password into the dashboard by hand.)

Migrations run automatically on every deploy — `docker/production/
entrypoint.sh` calls `php artisan migrate --force` before starting
PHP-FPM, and fails the container (not silently serves a stale schema)
if migration fails, deliberately different from the dev entrypoint's
tolerant three-container race handling.

---

## 3. Object Storage: Cloudflare R2 (or S3/Spaces)

`docs/adr/0010-media-platform.md` built the Media platform against
Laravel's `Storage` abstraction from the start — switching disks is
config only, confirmed by reading `config/filesystems.php`'s `s3` disk
stub, which already supports a custom `endpoint` and
`use_path_style_endpoint` (exactly what any S3-compatible provider,
not just AWS, needs).

**Why R2 over S3**: zero egress fees (a real cost difference for a
media-heavy app once it has real traffic) and a generous free tier —
a better fit for a portfolio project's actual usage pattern than a
functionally-identical AWS bucket. Any S3-compatible provider (AWS S3,
DigitalOcean Spaces, Backblaze B2) works with the same env shape.

1. Create an R2 bucket in the Cloudflare dashboard, and an API token
   scoped to it (Object Read & Write).
2. Set on the backend service:

```
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=<r2 access key id>
AWS_SECRET_ACCESS_KEY=<r2 secret access key>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket name>
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=<your R2 public bucket URL or custom domain, if configured>
```

No code deploys with this change — it's a live env var update on an
already-running service, matching the ADR's original "no code change"
decision exactly.

**Virus scanning**, named as a real gap since Milestone 12 and
deferred to this milestone: not implemented as code, because there's
no scanning service to test against without live infrastructure this
milestone's scope excludes. The concrete recommendation: enable your
object-storage provider's own malware-scanning add-on if it has one,
or run a [ClamAV](https://www.clamav.net/) sidecar container and wire
`MediaService::storeUpload()` to scan before persisting — a real,
scoped future addition once this project has an actual scanning
backend to integrate against, not speculative code with nothing to
verify it.

---

## 4. Cross-Domain Auth: One Root Domain, Two Subdomains

`docs/adr/0006-authentication-architecture.md` named this the one
real blocker Sanctum's cookie-session auth has in production: two
unrelated domains (`*.vercel.app` + `*.up.railway.app`) can't share a
`SameSite=Lax` session cookie. The fix needs no code — Sanctum, CORS,
and the session config are already fully env-driven
(`config/cors.php`, `config/sanctum.php`, `config/session.php` — all
read from env vars with no hardcoded values). Point the frontend and
backend at **custom subdomains of the same registrable domain**:

- Frontend on Vercel: a custom domain, e.g. `app.yourdomain.com`.
- Backend on Railway: a custom domain, e.g. `api.yourdomain.com`.

Subdomains of the same registrable domain count as **same-site** for
`SameSite` cookie purposes (the relevant unit is the eTLD+1, not the
full hostname) — `SameSite=Lax` (this project's existing default)
works across them with no code change and no need for the
`SameSite=None` + third-party-cookie complications a genuinely
cross-site setup would require.

Backend environment:

```
APP_URL=https://api.yourdomain.com
FRONTEND_URLS=https://app.yourdomain.com
SANCTUM_STATEFUL_DOMAINS=app.yourdomain.com
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
```

Frontend environment:

```
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

DNS: a `CNAME` (or Vercel/Railway's own recommended record type) for
each subdomain, pointed at the platform's provided target — configured
in each platform's own custom-domain dashboard, which also handles
TLS certificate provisioning automatically.

---

## 5. Backend: Railway

Railway builds directly from `docker/production/php.Dockerfile`
(multi-stage: a `vendor` stage installs Composer dependencies with
`--no-dev --optimize-autoloader`, a `runtime` stage is the actual
PHP-FPM image — no bind mounts, no dev tooling, OPcache on). Verified
locally before this milestone was called done: `docker build -f
docker/production/php.Dockerfile .` succeeds, and the built image's
`php -v` confirms OPcache active and `pdo_pgsql`/`pdo_sqlite` both
loaded.

**Three Railway services from one repo**, each pointed at the same
Dockerfile with a different start command — this is Railway's own
idiomatic process-supervision model (each service is independently
health-checked and restarted by the platform, the same real guarantee
a hand-rolled Supervisor config inside the container would provide,
without maintaining that config):

| Service | Start command | Purpose |
|---|---|---|
| `backend` | `php-fpm` (the Dockerfile's own `CMD`) + a reverse proxy in front (see below) | Serves HTTP |
| `queue` | `php artisan queue:work --tries=3` | Background jobs (sync, AI generation, media downloads) |
| `scheduler` | `php artisan schedule:work` | The one registered daily task (site metadata refresh) |

PHP-FPM alone doesn't speak HTTP — Railway needs something in front of
it the way `docker/caddy/Caddyfile` does for local dev. Either add a
minimal Caddy/Nginx sidecar service reusing that same Caddyfile
pattern, or switch the `backend` service's production image to
`php:8.3-apache`/`FrankenPHP` if a single-process HTTP-serving image
is preferred — a real, small decision left to whoever runs this
deploy, not prescribed further here since it doesn't change anything
else in this runbook.

**Health check**: point Railway's HTTP health check at
`GET /api/v1/health` (not Laravel's own `/up`, which stays reserved for
generic infra probes per `docs/adr/0004-backend-foundation.md`) — it's
public, unauthenticated, and checks both the database and queue
(`docs/adr/0016-observability.md`), so a failing dependency actually
fails the check instead of a bare "process is alive" signal.

**Required environment variables** (beyond §2–4 above):

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate fresh with `php artisan key:generate --show`, never reuse a dev key>
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_JSON=true
SENTRY_LARAVEL_DSN=<your Sentry project DSN, if using §1's optional step>
ANTHROPIC_API_KEY=<production key>
AI_PROVIDER=anthropic
QUEUE_CONNECTION=database
CACHE_STORE=database
```

`LOG_STACK=stderr` + `LOG_JSON=true`: Railway captures stdout/stderr
directly into its own log viewer, so logs need to go there, and
`docs/adr/0016-observability.md`'s JSON tap makes each line a real
structured event Railway (or anything ingesting its logs) can parse.

---

## 6. Frontend: Vercel

Next.js needs no Dockerfile — Vercel builds it natively. Point a new
Vercel project at this repo, confirm it detects Next.js and the
project root correctly (this repo's frontend lives at the repo root,
not a `frontend/` subdirectory — Vercel's root-directory setting should
stay default).

**Required environment variables**:

```
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

That's the only one the frontend reads at runtime
(`src/lib/api-client.ts`) — everything else (AI provider keys, DB,
storage credentials) lives on the backend only, never shipped to the
client.

---

## 7. Secrets

- `.env` has never been committed (verified via `git log --all -- '**/.env'`
  during this milestone — empty result) and is gitignored in both the
  repo root and `backend/`.
- Every secret above (DB password, `APP_KEY`, AI provider keys, R2
  credentials, Sentry DSN) is set through Railway's/Vercel's own
  environment variable UI, never written to a file this repo tracks.
- `APP_KEY` must be freshly generated for production
  (`php artisan key:generate --show`, copy the output into Railway's
  env vars) — never reuse the value from any developer's local `.env`.
- Rotate the Anthropic/Gemini API key used during Milestone 14's local
  development before using it in production, the same way any
  key that's been visible in a local `.env` during development should
  be rotated before its first real production use.

---

## 8. Post-Deploy Checklist

- [ ] `GET https://api.yourdomain.com/api/v1/health` returns `200`
      with `"status": "ok"` for both `database` and `queue`.
- [ ] Log in at `https://app.yourdomain.com` and confirm the session
      cookie persists across a page reload (the real test of §4's
      cross-domain configuration actually working).
- [ ] Connect a real WordPress site and run a sync — confirms the
      queue worker service is actually processing jobs, not just
      running.
- [ ] Upload a media file and confirm it's retrievable — confirms R2
      credentials and `MEDIA_DISK=s3` are correctly wired.
- [ ] If a Sentry DSN was configured, trigger a deliberate test error
      (a malformed request the `default` branch of
      `ApiExceptionHandler` would catch) and confirm it appears in the
      Sentry project dashboard — the one piece of Milestone 18's work
      this project couldn't verify live without a real DSN.
- [ ] Confirm `APP_DEBUG=false` — a debug-mode 500 page leaking stack
      traces to real users is the single most common production
      misconfiguration this checklist exists to catch.

---

## 9. What This Runbook Deliberately Doesn't Cover

- **A CDN in front of R2/Vercel** — Vercel's own edge network already
  serves the frontend; a dedicated CDN layer for media is real future
  scope once real traffic patterns justify it, not built ahead of
  need.
- **Blue/green or canary deploys** — Railway's default (build, health
  check, cut over) is proportional for this project's scale; a more
  elaborate rollout strategy is real future scope if this app ever
  needs zero-downtime guarantees stronger than that.
- **A CI/CD pipeline that deploys automatically on merge to `master`**
  — `docs/adr/0014-frontend-testing-and-ci.md`'s GitHub Actions
  workflow validates every PR; wiring it to also trigger a Vercel/
  Railway deploy is a small, real addition once this runbook's manual
  steps have been run at least once successfully.
