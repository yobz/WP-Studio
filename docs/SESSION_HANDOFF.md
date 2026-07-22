# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-22 — End of Milestone 18 (Observability)

**Milestone state.** Milestone 18 is implemented and validated —
`docs/adr/0016-observability.md` has the full reasoning.
**Not yet committed or pushed** — waiting on explicit approval per this
project's standing rule. `docs/ROADMAP.md`, `docs/PROJECT.md`, and
`docs/DEVLOG.md` are already updated to reflect it as complete;
`docs/MILESTONE_REPORT_M18.md` has the full independent review.

**New: real health checks, structured logging, and Sentry — all
composed from integration points earlier milestones already built.**
`/api/v1/health` now checks the queue as well as the database.
`LOG_JSON=true` produces genuine single-line JSON logs. `sentry/
sentry-laravel` is wired but has **no DSN configured** — see gotcha #1.
A new `LogApiRequests` middleware logs one line per request.
OpenTelemetry and frontend Sentry were deliberately **not**
implemented this milestone — see `docs/adr/0016-observability.md`'s
"What Wasn't Built" section before reconsidering either.

**Three things worth knowing before touching this again.**

1. **Sentry has no DSN in this repo, by design.** `SENTRY_LARAVEL_DSN`
   is empty in both `.env` and `.env.example` — the SDK is a safe
   no-op without one (confirmed: 144/144 tests pass, a live request
   completed normally with the integration active). If you want to see
   a real error land in a real Sentry project, you need to create a
   free Sentry project and set `SENTRY_LARAVEL_DSN` to its DSN — that's
   the only step left for live verification.
2. **`config/sentry.php` is intentionally sparse.** Only `dsn`,
   `environment`, `send_default_pii` (false — never leak PII by
   default), `sample_rate`, `traces_sample_rate` (0.0 — error
   monitoring only, no APM), and `ignore_transactions` are set
   explicitly; everything else falls back to the package's own defaults
   via `mergeConfigFrom()`. Don't copy the full
   `vendor/sentry/sentry-laravel/config/sentry.php` stub over this —
   it enables a lot of tracing/breadcrumb toggles this project
   deliberately doesn't use.
3. **`LOG_JSON` is off by default, on purpose.** Local `tail -f
   storage/logs/laravel.log` stays human-readable unless you explicitly
   set `LOG_JSON=true` — a real deployment would set it. Don't flip the
   default without checking whether any local-dev tooling expects the
   plain-text format.

**Immediate next step.** Milestone 19 (Cloud Deployment & Security
Hardening) is next per `docs/ROADMAP.md` — deploy to Vercel/Railway (or
alternatives), migrate SQLite → MySQL/PostgreSQL if appropriate,
configure object storage, review secrets/rate-limiting, and a security
audit — but is **explicitly not started**, waiting for approval.
Milestone 18 itself also still needs explicit commit/push approval.

**Known live gotchas (carried forward, still accurate).**
- Docker (Milestone 15): `docker compose up` is a real, working
  alternative to the bare-metal setup — see that milestone's own
  Session Handoff entry in `docs/DEVLOG.md` history for its specific
  gotchas.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` and a brief settle wait before interacting; a
  *full-page* `page.goto()` reload is meaningfully slower than
  client-side `Link` navigation (the auth guard re-runs its session
  check from scratch) — give it 3–4s, not 1–1.5s.
- `php artisan serve`'s first one or two requests after a cold start
  can be slow enough to drop a connection or return a truncated/non-
  JSON body — not an app bug, give the dev server a moment to warm up
  before scripting live verification against it.
- A stack trace pointing entirely inside a dependency's own bundled
  internals, right after a `node_modules` change, is this project's
  recurring stale `.next`/`bootstrap/cache` build-cache pattern — see
  `docs/ENGINEERING_JOURNAL.md`.
- `axe-core` is a real transitive dependency, never delete it during
  cleanup. `playwright` is installed with `--no-save` and uninstalled
  again after ad hoc live verification, every time.
- `composer require`/`composer dump-autoload` can exceed the default
  2-minute tool timeout on this Windows/OneDrive-synced checkout — let
  it run in the background and wait for the notification rather than
  assuming it hung.
- Never print any part of an API key/credential/DSN into tool output or
  logs.
- Demo login: `test@example.com` / `password`.

**Validation status as of this session.** Backend: `php artisan test`
— **144/144 passing** (142 unchanged + 2 new `HealthCheckTest` cases).
`./vendor/bin/pint --test` (full-repo) — clean. Frontend: untouched
this milestone (no frontend changes) — last known state 20/20 passing,
typecheck/lint/build clean (Milestone 17). Live verification: a real
running server correctly returned `degraded`/503 from
`/api/v1/health` against genuine pre-existing failed jobs in the dev
database; `LOG_JSON=true` confirmed to produce valid JSON log lines;
Sentry integration confirmed not to alter any request path's behavior
with no DSN configured.
