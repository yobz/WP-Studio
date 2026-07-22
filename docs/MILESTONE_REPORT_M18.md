# Milestone 18 Report

## Date

2026-07-22

---

## Objective

Implement structured logging, health checks, Sentry/OpenTelemetry
integration, request tracing, and operational metrics using the
integration points established in earlier milestones. Per explicit
guidance carried into this milestone: keep observability proportional
to the project's goals, demonstrate real familiarity with error
monitoring and logging, and avoid introducing unnecessary
infrastructure — a lightweight Sentry integration (or similarly scoped
solution), structured logging, and basic monitoring are sufficient.

---

## Executive Summary

Milestone 18 is complete. `docs/adr/0004-backend-foundation.md` named
this exact shape back in Milestone 4 — `AssignRequestId`,
`ApiExceptionHandler`, and commented `.env.example` placeholders for
Sentry/OpenTelemetry were all built as groundwork, with the explicit
note that adding Sentry later would be "a package install plus config,
not a restructuring." This milestone was that composition, not new
infrastructure from zero.

**Structured logging**: a Monolog channel tap swaps in a JSON
formatter, opt-in via `LOG_JSON` (off by default, local dev stays
human-readable). **Health checks**: `/api/v1/health` now checks the
queue as well as the database — zero new checker code, reusing
Milestone 11's `QueueHealthChecker`. **Sentry**: the official
`sentry/sentry-laravel` SDK, wired via `bootstrap/app.php`'s existing
`withExceptions()` closure, DSN-optional (no DSN is configured in this
repo — a safe no-op, structurally verified but not live-verified
against a real Sentry project). **Request logging**: a new
`LogApiRequests` middleware logs one line per request, correlated by
the request ID already shared since Milestone 4.

**OpenTelemetry and frontend Sentry were deliberately not
implemented** — named, documented scope cuts, not gaps. No
trace-collection backend exists in this project's deployment story;
frontend error monitoring stays out of a "lightweight" brief already
satisfied by a real backend integration.

---

## Architecture Review

Read `docs/ROADMAP.md`'s Milestone 18 entry,
`docs/adr/0004-backend-foundation.md` (which had already named
`AssignRequestId`/`ApiExceptionHandler` as the intended Sentry
integration point and left `.env.example` placeholders), and the
existing `DatabaseHealthChecker`/`QueueHealthChecker`/
`SystemHealthService` stack from Milestone 11. Confirmed via
`php artisan route:list` that `/api/v1/health` already existed
(database-only) as a separate, public endpoint from Laravel's own
`/up` — exactly matching ADR-0004's description of the two staying
deliberately separate (app-level vs. infrastructure-level probes).
Read Laravel 12's actual `/up` route source
(`ApplicationBuilder::buildRoutingCallback()`) directly rather than
assume its behavior, and Laravel's core
`Handler::$internalDontReport` list directly rather than assume which
exceptions get reported.

---

## Architecture Drift Review

No structural drift — every piece added this milestone composes
existing, previously-built integration points rather than introducing
new architecture. The one real decision was scope: what counts as
"lightweight" observability for a portfolio project. Evaluated and
rejected: full OpenTelemetry (no collector/backend exists anywhere
this app runs), Laravel Pulse (a heavier first-party dashboard with
its own storage tables — more infrastructure than the brief calls
for), frontend Sentry (a second SDK/DSN/config surface not justified
alongside a working backend integration), and enabling Sentry's
performance-tracing sampling (out of scope for "error monitoring").

---

## What Was Built

**Structured logging**: `app/Logging/JsonFormatterTap.php` (a Laravel
channel "tap" swapping in `Monolog\Formatter\JsonFormatter`), wired
onto the `single`/`daily`/`stderr` channels in `config/logging.php`,
gated by a new `LOG_JSON` env var (default `false`).

**Health checks**: `HealthController` now injects and calls
`QueueHealthChecker` alongside the existing `DatabaseHealthChecker`;
`status` degrades to `degraded`/503 if the database is unreachable or
any queue job has failed.

**Sentry**: `composer require sentry/sentry-laravel`. `config/
sentry.php` (new, intentionally minimal — `dsn`, `environment`,
`send_default_pii: false`, `sample_rate: 1.0`,
`traces_sample_rate: 0.0`, `ignore_transactions`). `bootstrap/app.php`
gained `Sentry\Laravel\Integration::handles($exceptions)` inside the
existing `withExceptions()` closure, alongside the pre-existing
`ApiExceptionHandler::register($exceptions)`. `.env`/`.env.example`
gained `SENTRY_LARAVEL_DSN` (empty).

**Request logging**: `app/Http/Middleware/LogApiRequests.php` (new),
registered via `$middleware->api(append: [LogApiRequests::class])` in
`bootstrap/app.php` — runs after `AssignRequestId` (prepended), so
every logged line already carries the shared `request_id`.

**Tests**: `tests/Feature/HealthCheckTest.php` (new) — asserts the
endpoint requires no authentication and reports `ok`/200 when healthy,
and `degraded`/503 when a failed job exists (following the exact
`failed_jobs`-insertion pattern already used by
`BackgroundJobsTest.php`'s equivalent `SystemHealth` coverage).

**Documentation**: `docs/adr/0016-observability.md`, plus amendments to
`docs/PROJECT.md` (a new Milestone 18 section, a Stack-table row, five
Known Limitations bullets, one bullet in the Milestone 6 section
updated from "not implemented" to resolved), `docs/ROADMAP.md` (marked
complete), `docs/DEVLOG.md`, and `docs/SESSION_HANDOFF.md`.

---

## Validation

- `php artisan test` — **144/144 passing** (142 unchanged + 2 new).
- `./vendor/bin/pint --test` (full-repo) — clean.
- **Live verification against a real running server** (`php artisan
  serve`), not just unit tests:
  - `GET /api/v1/health` returned `degraded`/503 — the real dev
    database has 4 genuine, pre-existing failed jobs (dated a week
    before this milestone). This is the check correctly surfacing real
    state, not a bug this milestone introduced; left alone as
    out-of-scope dev-database cleanup.
  - `LOG_JSON=true` confirmed (via `php artisan tinker`) to produce a
    single valid JSON object per log line, including `message`,
    `context`, `level`, `level_name`, `channel`, `datetime`. Unset,
    confirmed unchanged from every prior milestone's plain-text format.
  - The full test suite and a live request both completed normally
    with the Sentry integration wired in and no DSN configured,
    confirming the documented no-op behavior rather than assuming it.
- Frontend: untouched this milestone (backend-only scope); no new
  frontend validation needed beyond Milestone 17's last-known-clean
  state.

---

## Self Review

Re-read every changed file. Confirmed `LogApiRequests` is registered
*after* `AssignRequestId` (append, not prepend) so `request_id` context
sharing happens before this middleware's log line is written — checked
by reading `bootstrap/app.php`'s actual middleware registration order,
not assumed. Confirmed no custom exception-filtering code was needed
for Sentry by reading Laravel's actual `Handler::$internalDontReport`
source rather than trusting memory of Laravel's exception-reporting
behavior — the list matches `ApiExceptionHandler::render()`'s handled
cases exactly (`AuthenticationException`, `AuthorizationException`/
`AccessDeniedHttpException`, `ModelNotFoundException`/
`NotFoundHttpException`, `TooManyRequestsHttpException`,
`ValidationException` — all subtypes of the excluded list). Confirmed
`config/sentry.php`'s sparse shape is safe (not missing required keys)
by reading the package's own `mergeConfigFrom()` call, which merges
unpublished keys from the package's own default config rather than
requiring every key to be present locally.

---

## Production Readiness

A real deployment now has three concrete levers this milestone added:
set `LOG_JSON=true` for machine-parseable logs, set a real
`SENTRY_LARAVEL_DSN` for error alerting, and point an uptime monitor or
orchestrator readiness probe at `/api/v1/health` for a real
dependency-aware check instead of "is the process alive." None of
these require further code changes — only environment configuration,
exactly the "package install plus config, not a restructuring" ADR-0004
predicted.

---

## Technical Debt Resolved

- **No structured/machine-parseable logging**, an implicit gap since
  Milestone 4 (logs existed and carried request-ID context, but only
  in a human-oriented line format) — resolved, opt-in.
- **`/api/v1/health` only checked the database**, not the queue, since
  Milestone 4 — resolved, reusing Milestone 11's existing checker.
- **Sentry/OpenTelemetry documented but not implemented**, named since
  Milestone 4 — Sentry resolved; OpenTelemetry explicitly re-affirmed
  as deferred, not silently dropped.

---

## Deferred Work

- **A real Sentry DSN and live error-arrival verification** — needs
  the project owner to create a Sentry project and provide its DSN.
- **Frontend error monitoring** — a real, separate future milestone or
  ad hoc addition if frontend error volume ever justifies it.
- **OpenTelemetry** — deferred until a real trace-collection backend
  exists in this project's deployment story, a natural fit to revisit
  alongside Milestone 19's cloud deployment work if ever justified.
- **Clearing the 4 stale `failed_jobs` rows found during live
  verification** — pre-existing dev-database state, unrelated to this
  milestone's scope.

---

## Risks

- **Sentry's behavior with a real DSN is unverified** — the
  integration is structurally correct and matches the SDK's documented
  Laravel 12 installation steps exactly, but no live error has actually
  been captured and viewed in a real Sentry project. Low risk: this is
  a well-established, officially maintained SDK following its own
  documented integration path exactly, not custom code.
- **`LOG_JSON` being off by default means a real deployment must
  remember to set it** — a one-line env change, but a manual step
  nonetheless. Documented explicitly in `docs/SESSION_HANDOFF.md` and
  `.env.example`'s own comment so it isn't forgotten.

---

## Recommendation for Milestone 19

Per `docs/ROADMAP.md`, Milestone 19 (Cloud Deployment & Security
Hardening) is next — deploy to Vercel/Railway (or alternatives selected
during that milestone's own review), migrate SQLite to MySQL/
PostgreSQL if appropriate, configure object storage (S3/R2) for media,
review environment configuration/rate limiting/secrets management, and
a security audit. A real Sentry DSN and `LOG_JSON=true` would both be
natural, low-effort additions to that milestone's production
configuration work. Waiting for explicit approval before starting, per
this milestone's own stop condition.
