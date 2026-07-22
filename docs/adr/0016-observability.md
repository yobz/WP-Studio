# 0016 — Observability

**Status:** Accepted (Milestone 18)

## Decision

Proportional observability, built on the integration points
`docs/adr/0004-backend-foundation.md` deliberately laid down and named
back in Milestone 4: structured (opt-in JSON) logging, a real
`/api/v1/health` check (database *and* queue, not just "the PHP process
booted"), backend error monitoring via the official Sentry Laravel SDK
(DSN-optional — a safe no-op without one), and a lightweight one-line-
per-request access log. **OpenTelemetry is explicitly not
implemented** — no collector or trace-storage backend exists in any
environment this app runs in today, and standing it up purely to have
traces nobody's looking at would be exactly the "unnecessary
infrastructure" this milestone was scoped to avoid.

## Context

`docs/adr/0004-backend-foundation.md` named this milestone's shape four
releases ago: `AssignRequestId` (request-ID generation and log-context
sharing), `ApiExceptionHandler` (one centralized error→JSON render
path), and commented `.env.example` placeholders for
`SENTRY_LARAVEL_DSN`/`OTEL_EXPORTER_OTLP_ENDPOINT` were all built as
groundwork with the explicit note: "Adding Sentry later is a package
install plus config, not a restructuring." Milestone 11 separately
built `DatabaseHealthChecker` and `QueueHealthChecker` for the
dashboard's `SystemHealth` widget. This milestone's actual job was
composition — wiring already-built pieces into real observability
surfaces — not building new infrastructure from zero. Explicit scoping
guidance carried into this milestone: keep it proportional, demonstrate
real familiarity with error monitoring and logging, avoid unnecessary
infrastructure.

## Structured Logging, Opt-In

`AssignRequestId` has shared `request_id` into every log line's context
since Milestone 4 — what was still missing was a *machine-parseable*
line format to put that context in. Added `App\Logging\JsonFormatterTap`
(a Laravel "channel tap," the framework's own documented mechanism for
swapping a channel's Monolog formatter without duplicating its
config) and wired it, conditionally, onto the `single`, `daily`, and
`stderr` channels via a new `LOG_JSON` env var — `false` by default, so
local `tail -f storage/logs/laravel.log` stays human-readable; a real
deployment sets it to `true` for CloudWatch/Datadog/ELK-style ingestion.
Verified live, not just by reading the config: with `LOG_JSON=true`,
`Log::info(...)` produces one JSON object per line
(`{"message":...,"context":{...},"level":200,...}`); with it unset,
output is unchanged from every prior milestone's log format.

## Real Health Checks, Not Just "Process Alive"

`api/v1/health` already existed (Milestone 4, per the ADR above) but
only checked the database. `QueueHealthChecker` already existed
(Milestone 11, serving `SystemHealthService`) but wasn't wired into it.
The fix was two lines of composition, not new code: `HealthController`
now calls both checkers, and `status` degrades (HTTP 503) if the
database is unreachable *or* there are any failed queue jobs. Laravel's
own built-in `/up` (kept, deliberately, as ADR-0004 specified — a
separate infrastructure-level probe unrelated to this app's own
dependencies) still exists unchanged; `/api/v1/health` is the
versioned, JSON, dependency-aware check a real uptime monitor or
orchestrator readiness probe would actually use.

**A genuine, honest finding during live verification**: hitting the
live endpoint against the real dev database returned `degraded` — four
real failed jobs sitting in `failed_jobs`, dated 2026-07-16, predating
this milestone by nearly a week (leftover from earlier live-verification
sessions, most likely Milestone 14's AI-generation testing). Left
alone, deliberately — this is exactly the check working correctly on
real data, not a regression this milestone introduced, and investigating
or clearing stale dev-database rows is unrelated to Milestone 18's
actual scope.

## Sentry: A Real Integration, Honestly Unverified Live

`composer require sentry/sentry-laravel` (the official SDK, not a
hand-rolled HTTP client to Sentry's ingest API — reinventing exception
serialization, breadcrumbs, and release tagging would be real,
unjustified scope for what a maintained SDK already does correctly).
Wired via `Sentry\Laravel\Integration::handles($exceptions)` inside
`bootstrap/app.php`'s existing `withExceptions()` closure — the exact
single choke point ADR-0004 predicted, alongside the pre-existing
`ApiExceptionHandler::register($exceptions)` call.

**No custom filtering code was needed to keep 4xx noise out of
Sentry.** Laravel's own `Illuminate\Foundation\Exceptions\Handler`
already excludes `AuthenticationException`, `AuthorizationException`,
the `HttpException` family (covers `NotFoundHttpException`,
`AccessDeniedHttpException`, `TooManyRequestsHttpException` — everything
Symfony's `HttpException` is a parent of), `ModelNotFoundException`, and
`ValidationException` from its `report()` pipeline by default — which
is exactly the same set `ApiExceptionHandler::render()` already
special-cases into clean 4xx JSON responses. Confirmed by reading
`Handler::$internalDontReport` directly rather than assuming. Sentry
only ever sees what reaches `ApiExceptionHandler`'s `default` branch —
genuinely unexpected 500s — which is the entire point of error
monitoring: signal, not noise.

`config/sentry.php` is intentionally minimal — `dsn`, `environment`,
`send_default_pii: false` (this is a SaaS handling real user emails and
workspace data; never leak PII to a third party by default),
`sample_rate: 1.0` (capture every reported error), and
`traces_sample_rate: 0.0` (no performance tracing/APM — error
monitoring only, matching the "lightweight" brief). Every other key
Sentry's SDK supports (breadcrumb toggles, tracing sub-flags) is left
unset and falls back to the package's own sane defaults via its
`mergeConfigFrom()`, not copied wholesale into this repo's config —
enumerating dozens of toggles this project doesn't use would be
padding, not documentation.

**Honest scope of what was verified.** No Sentry DSN exists in this
repo — `SENTRY_LARAVEL_DSN` is unset by design (the same DSN-optional
pattern `docs/adr/0012-ai-content-generation.md` used for provider API
keys). Verified structurally: all 144 backend tests pass with the SDK
wired in, a live request against a running server completed normally
with the integration active, and reading the SDK's own documented
behavior confirms an unset DSN is a safe no-op (the SDK simply never
initializes a client). What's **not** verified is a real error actually
arriving in a real Sentry project dashboard — that needs a real DSN,
which only the project owner can provide, the same honest gap
`docs/adr/0012-ai-content-generation.md`'s Live Verification section
already established a precedent for documenting rather than hiding.

## Lightweight Request Logging, Not Distributed Tracing

Added `LogApiRequests` middleware — one structured log line per API
request (`method`, `path`, `status`, `duration_ms`), registered after
`AssignRequestId` in the `api` middleware group so every line
automatically carries the already-shared `request_id`. This is
"request tracing" at the scale this project actually needs: a
grep-able (or, with `LOG_JSON=true`, queryable) access log correlated
by request ID across every log line a single request produced —
without a trace collector, span exporter, or any new infrastructure.

## What Wasn't Built, and Why

**OpenTelemetry.** `.env.example`'s `OTEL_EXPORTER_OTLP_ENDPOINT`
placeholder stays commented and unimplemented. Distributed tracing
exists to answer "where did time go across service boundaries" — this
app has exactly one backend process and one frontend, and no
collector/Jaeger/Tempo-equivalent backend exists in any environment it
runs in. Implementing OTel instrumentation with nowhere to send the
resulting spans would be observability theater, not real production
awareness.

**Frontend Sentry.** Deliberately backend-only this milestone. Adding
`@sentry/nextjs` doubles the SDK/DSN/config surface (sourcemap
uploads, a second project, client- vs. server-config split) for a
"lightweight" brief that already has a real, working backend
integration. The frontend's existing `ApiError`/`UNAUTHORIZED_EVENT`
pattern (`docs/adr/0006-authentication-architecture.md`) remains the
primary frontend error surface; frontend Sentry is real, named future
scope, not a silent gap.

**A new dedicated metrics/dashboard endpoint.** `/api/v1/system-health`
(Milestone 11, workspace-scoped dashboard widget) and the now-enhanced
`/api/v1/health` (public, dependency-aware) already provide the
operational visibility "basic monitoring" calls for. A third endpoint
duplicating either would be redundant infrastructure, not additive.

## Rejected Alternatives

**Full OpenTelemetry distributed tracing.** See above — no backend to
receive traces exists; implementing the instrumentation anyway would be
disproportionate to this project's actual deployment shape.

**A hand-rolled error-reporting HTTP client instead of
`sentry/sentry-laravel`.** Rejected — the official SDK correctly
handles exception serialization, breadcrumbs, release tagging, and
Laravel-specific integration (queue jobs, HTTP client calls) that a
custom client would have to reimplement, incompletely, for no real
benefit.

**Laravel Pulse** (a related, heavier first-party observability
dashboard — request/queue/cache metrics with its own storage table and
UI). Considered as a single-package way to get "operational metrics."
Rejected: it's a genuinely bigger piece of infrastructure (a new
`pulse_*` table set, a dashboard route, background aggregation) than
"lightweight... avoid unnecessary infrastructure" calls for, and the
existing `SystemHealthService`/`/api/v1/health` combination already
covers this milestone's actual monitoring needs without it.

**Enabling Sentry performance tracing** (`traces_sample_rate > 0`).
Rejected — the brief asked for error monitoring, not APM; enabling
tracing would add sampling decisions, transaction naming, and a whole
second Sentry product surface this milestone doesn't need.

## Validation

- `php artisan test` — **144/144 passing** (142 unchanged + 2 new
  `HealthCheckTest` cases: healthy-with-no-auth-required, and
  degraded-with-503-on-a-failed-job).
- `./vendor/bin/pint --test` — clean.
- Live verification against a running server: `GET /api/v1/health`
  correctly returned `degraded`/503 against the real dev database's
  genuine (pre-existing, unrelated) failed jobs — a real signal, not a
  simulated one. `LOG_JSON=true` confirmed to produce valid single-line
  JSON log output; unset confirmed unchanged from every prior
  milestone's format. The Sentry integration confirmed not to alter
  behavior or break any request path with no DSN configured.

## Deferred Work

- **A real Sentry DSN and live error-arrival verification** — the
  integration is code-complete and structurally verified; confirming
  an actual error appears in an actual Sentry project needs a DSN only
  the project owner can provide.
- **Frontend error monitoring (`@sentry/nextjs` or equivalent)** —
  deliberately out of this milestone's scope; a real future addition,
  not a silent gap.
- **OpenTelemetry** — deferred until a real trace-collection backend
  exists in this project's deployment story (a natural fit for
  Milestone 19's cloud deployment work, if ever justified by real
  cross-service latency questions this app doesn't have yet with a
  single backend process).
- **Clearing the four stale `failed_jobs` rows found during live
  verification** — pre-existing dev-database state, unrelated to this
  milestone's actual scope; noted, not acted on.
