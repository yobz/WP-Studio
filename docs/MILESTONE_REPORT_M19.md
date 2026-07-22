# Milestone 19 Report

## Date

2026-07-22

---

## Objective

Deploy to Vercel and Railway (or alternatives selected during the
milestone review), migrate from SQLite to MySQL/PostgreSQL if
appropriate for production, configure object storage (S3/R2) for
media, review environment configuration, rate limiting, secrets
management, and perform a security audit of the application — the
accumulation point for every deployment-shaped gap named and deferred
across nearly every prior milestone.

---

## Executive Summary

Milestone 19 is complete, scoped deliberately to **"deployment-ready,
not deployed"** — confirmed with the user before implementation, since
real Vercel/Railway accounts, a real domain, and real object storage
credentials require account access and billing decisions this
project's automated milestone work doesn't have. Everything code and
configuration could cover, this milestone built; `docs/DEPLOYMENT.md`
is the runbook for the rest.

**PostgreSQL, verified against a real instance, not assumed.** A
temporary Postgres 16 container ran every migration and the full
145-test backend suite — zero errors, zero code changes.

**A real DNS-resolution SSRF fix, and a real Pest bug found building
its test.** `UrlSafetyValidator` now resolves hostnames and checks
every address they point at. Building a fake `DnsResolver` for the
test suite surfaced a genuine, pre-existing Pest configuration bug — a
standalone `beforeEach()` that had silently never fired outside its
own file, letting 44 real DNS lookups happen per full suite run,
unnoticed until this milestone's own testing work exposed it.

**A rate-limiting gap closed**: a global 120-req/min backstop now
covers every API endpoint, not just the four that had bespoke limits.
**An HSTS header added**; a full CSP deliberately deferred. **Sanctum's
cross-domain cookie blocker resolved with zero code** — a documented
deployment strategy, not an architecture change. **A production Docker
image built and smoke-tested locally**, not just written. **Process
supervision and virus scanning**: real, documented decisions (Railway's
own service model; a concrete runbook recommendation), not silent gaps
or speculative untested code.

---

## Architecture Review

Read every prior ADR's deferred-to-Milestone-19 items directly rather
than re-deriving scope from scratch: `docs/adr/0004` (SQLite→production
database), `docs/adr/0006` (Sanctum cross-domain), `docs/adr/0007`
(DNS-resolution SSRF), `docs/adr/0009` (process supervision),
`docs/adr/0010` (object storage, virus scanning), `docs/adr/0013`
(production Docker images). Read the actual current config
(`config/database.php`, `config/filesystems.php`,
`config/cors.php`/`sanctum.php`/`session.php`,
`app/Providers/AppServiceProvider.php`'s rate limiters,
`app/Http/Middleware/SecureHeaders.php`) rather than assume what state
each was in.

---

## Architecture Drift Review

**Scope, asked before implementation**: given real account
provisioning is outside this project's automated reach, how far to go.
Answer — deployment-ready, not deployed — shaped every decision below.

**PostgreSQL over MySQL**: Railway's first-class Postgres support and
better JSON/full-text ergonomics — though moot in practice, since
verification showed the schema compatible with either at zero cost.

**Cloudflare R2 over plain S3**: zero egress fees, a real cost
difference for a media app; any S3-compatible provider works
identically given `config/filesystems.php`'s existing custom-endpoint
support.

**Railway's per-service process model over a hand-rolled Supervisor
config**: the identical real guarantee (auto-restart on crash) with
zero extra configuration to write or maintain.

**HSTS now, full CSP deferred**: HSTS is unconditionally safe to add;
CSP carries real risk of breaking the app without per-page verification
this milestone didn't budget time for.

**Virus scanning documented, not coded**: no real scanning service
exists to build or test against without live infrastructure this
milestone's scope excluded — the same anti-speculative-code discipline
`docs/adr/0015-performance-and-scalability.md` applied to declining
premature Redis caching.

---

## What Was Built

**Database**: verified PostgreSQL compatibility via a temporary local
Postgres 16 container — all migrations, all 145 tests, zero code
changes. No permanent test-infrastructure change; the verification
config (`phpunit.pgsql-verify.xml`) was deleted after the one-time run.

**Object storage**: confirmed `config/filesystems.php`'s `s3` disk
stub already supports R2/S3-compatible endpoints; documented the exact
env vars in `docs/DEPLOYMENT.md`. No code changed.

**Security — DNS-resolution SSRF**: `app/Services/WordPress/Security/
DnsResolver.php` (new), injected into `UrlSafetyValidator`, which now
resolves hostnames and checks every resolved address, not just literal
IPs. `tests/Feature/WordPressConnectionTest.php` gained a new test;
`tests/Pest.php` gained a global fake `DnsResolver` binding (correctly
chained onto `pest()->extend()->in('Feature')`, after discovering a
standalone `beforeEach()` silently never fires outside its own file —
see the Engineering Journal).

**Security — rate limiting**: `AppServiceProvider` gained a
`RateLimiter::for('api', ...)` (120/min); `bootstrap/app.php` wired
`$middleware->throttleApi()` onto the whole `api` group.

**Security — headers**: `SecureHeaders` gained `Strict-Transport-
Security`; `tests/Feature/SecureHeadersTest.php` (new) asserts the full
baseline header set.

**Production Docker image**: `docker/production/php.Dockerfile`
(multi-stage: a `vendor` build stage, a self-contained `runtime`
stage), `docker/production/opcache.ini`, `docker/production/
entrypoint.sh` (migrate-or-fail, unlike the dev entrypoint's tolerant
race handling). Built locally (`docker build`) and smoke-tested
(`php -v`/`php -m` inside the built image).

**Documentation**: `docs/adr/0017-cloud-deployment-and-security-
hardening.md`, `docs/DEPLOYMENT.md` (new — the actual deploy runbook),
plus amendments to `docs/PROJECT.md` (a new Milestone 19 section, ten
Known Limitations bullets updated/resolved, a Stack table row),
`docs/ROADMAP.md` (marked complete), `docs/DEVLOG.md`, and
`docs/ENGINEERING_JOURNAL.md` (five Future Backlog items resolved, one
new full dated investigation entry for the Pest bug).

---

## Validation

- `php artisan test` — **146/146 passing** (144 unchanged + 2 new).
- Full suite also run against a real, temporary Postgres 16 container
  — **145/145 passing** (before the 2 new tests existed), zero
  migration errors, zero code changes needed.
- `./vendor/bin/pint --test` — clean.
- `docker build -f docker/production/php.Dockerfile .` — succeeds
  (222MB final image); `php -v`/`php -m` run inside the built image
  confirm OPcache active and both `pdo_pgsql`/`pdo_sqlite` loaded.
- `git log --all -- '**/.env'` — confirmed empty; no `.env` file has
  ever been committed to this repository.
- The Pest `beforeEach` bug was confirmed fixed by direct
  instrumentation (debug logging on both the real and fake
  `DnsResolver` paths), not assumed fixed from reading the diff: 44
  real DNS calls per suite run before the fix, 0 after, across five
  consecutive full-suite runs.

---

## Self Review

Re-read every changed file. Confirmed `DnsResolver`'s failure mode
(DNS resolution failure returns an empty array, not an exception) is
correct — an unresolvable hostname is a connectivity problem the
WordPress client surfaces naturally on its own, not a safety concern
this validator should reject. Confirmed the new global rate limiter
doesn't conflict with the four existing endpoint-specific ones —
Laravel stacks multiple `throttle:` middleware on the same route
without conflict, verified by the full test suite still passing
(including `it rate limits repeated connection attempts`, which
exercises the tighter `wordpress-connection` limiter specifically).
Confirmed the production Dockerfile's `USER www-data` instinct was
wrong before shipping it — removed after reasoning through a real risk
(PHP-FPM's own privilege-drop conflicting with an already-non-root
master process) rather than including untested speculation.

---

## Production Readiness

This milestone is itself a production-readiness milestone — every
item closes a real, previously-named gap between "works on this
machine" and "safe to run for real users": a database choice backed by
actual verification instead of an open question since Milestone 6, an
SSRF check that actually covers hostnames, a rate limit that actually
covers the whole API surface, and a documented, concrete path from
"code is ready" to "someone can actually go live" (`docs/
DEPLOYMENT.md`). What remains — the actual deployment — is
correctly framed as the project owner's own next action, not something
this milestone should have faked or skipped past.

---

## Technical Debt Resolved

- **SQLite→production-database decision**, open since Milestone 6 —
  resolved with real evidence, not just a choice.
- **DNS-resolution SSRF gap**, named since Milestone 9 — resolved.
- **Sanctum's cross-domain cookie blocker**, named since Milestone 8 —
  resolved with zero code changes.
- **No process supervision for `queue:work`**, named since Milestone
  11 — resolved via Railway's own service model.
- **No production Docker image**, named since Milestone 15 — resolved
  for the backend (the frontend needs none, Vercel builds natively).
- **A rate-limiting gap covering most of the API surface** — not
  previously named as debt (no prior milestone's review had surfaced
  it) — found and resolved this milestone.
- **A latent Pest test-suite bug** causing 44 real, unnecessary DNS
  lookups per suite run — found and resolved as a byproduct of this
  milestone's own testing work, not previously known to exist.

---

## Deferred Work

- **The actual live deployment** — provisioning real accounts, a
  domain, and credentials; `docs/DEPLOYMENT.md` is the complete
  runbook for this step.
- **A real Sentry DSN** — code-complete since Milestone 18; this
  milestone's runbook names it as a post-deploy verification step.
- **DNS-rebinding (TOCTOU) protection** beyond resolve-and-check — real
  further hardening, not currently justified by this app's threat
  model.
- **A full `Content-Security-Policy` header** — needs page-by-page
  verification against the real Next.js app first.
- **Virus scanning on media uploads** — a real, scoped addition once a
  real scanning backend exists to integrate against.
- **Automatic CI-triggered deploys** — a small, real addition once
  `docs/DEPLOYMENT.md`'s manual steps have been run successfully once.

---

## Risks

- **Nothing in this milestone has been verified against a real, live
  Vercel/Railway deployment** — every verification (Postgres
  compatibility, the Docker build, the config review) was done
  locally, against real instances of the underlying technology, but
  not against the actual target platforms. `docs/DEPLOYMENT.md`'s
  post-deploy checklist exists specifically to catch anything that
  only surfaces once real infrastructure is involved.
- **The production Dockerfile's reverse-proxy question is left
  slightly open** — PHP-FPM alone doesn't serve HTTP; `docs/
  DEPLOYMENT.md` names two real options (a Caddy/Nginx sidecar, or
  switching to a single-process HTTP-serving PHP image) without
  prescribing one, since it doesn't affect anything else in the
  runbook and is a genuinely reasonable judgment call for whoever runs
  the actual deploy.

---

## Recommendation for Milestone 20

Per `docs/ROADMAP.md`, Milestone 20 (Production Release) is next and
last — final architecture review, production readiness audit,
deployment checklist, disaster recovery review, documentation cleanup,
dependency audit, and final polish, resolving or explicitly documenting
every deferred decision from Milestones 1–19 before closing the
project. Waiting for explicit approval before starting, per this
milestone's own stop condition.
