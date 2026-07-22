# 0017 — Cloud Deployment & Security Hardening

**Status:** Accepted (Milestone 19)

## Decision

**Deployment-ready, not deployed** — an explicit scope decision made
before any implementation, not a limitation discovered afterward.
Real Vercel/Railway accounts, a real domain, and real object-storage
credentials require account access and billing decisions this
project's automated milestone work doesn't have; provisioning them is
the project owner's own step, guided by the new `docs/DEPLOYMENT.md`
runbook this milestone wrote. Everything code/config could cover, it
does: PostgreSQL production-readiness (measured against a real
instance, not assumed), object storage wired for R2/S3, a real
DNS-resolution SSRF fix, a global API rate-limit backstop, an HSTS
header, a production-shaped multi-stage Docker image (built and
smoke-tested locally), and a fully documented cross-domain auth
strategy requiring zero code changes.

## Context

`docs/ROADMAP.md`'s Milestone 19 entry — deploy to Vercel/Railway,
migrate SQLite → MySQL/PostgreSQL if appropriate, configure object
storage, review environment/rate-limiting/secrets, security audit —
is the accumulation point for a long list of gaps named and explicitly
deferred to this exact milestone across nearly every prior ADR:
Sanctum's cross-domain cookie problem (`0006`), DNS-resolution SSRF
hardening (`0007`), the SQLite→production-database decision (`0004`),
`MEDIA_DISK=s3`/virus scanning (`0010`), production Docker images
(`0013`). This milestone's job was answering each one with evidence,
not further deferral.

## Scope: What "Deployment-Ready, Not Deployed" Means

Asked directly before starting: given this milestone mixes pure
code/config work with things requiring real external accounts, how far
to go. The answer — build everything code can cover, document a
runbook for the rest — shaped every decision below. Nothing here
required guessing at account structure or provisioning anything live;
every artifact (the Dockerfile, the config changes, the runbook) is
independently reviewable and testable without any of those.

## PostgreSQL: Verified, Not Assumed

`docs/adr/0004-backend-foundation.md` left MySQL vs. PostgreSQL an open
question since Milestone 6. Chose PostgreSQL — Railway's first-class
Postgres support, and better JSON/full-text primitives than MySQL for
a data model like this one's, though neither point actually mattered
in practice (see below). Rather than assume compatibility, spun up a
real Postgres 16 container, ran every migration against it, and ran
the **full 145-test backend suite** against it via a temporary
`phpunit.pgsql-verify.xml` (deleted after the run, not committed —
this was a one-time verification, not a permanent second CI target).
**Zero migration errors, zero test failures, zero code changes
needed.** `config/database.php`'s `pgsql` connection was already
present and correctly configured (Laravel's own stock stub, unmodified
since scaffolding) — this milestone's actual work was running the
verification that had never been done, not writing new compatibility
code.

## Object Storage: Cloudflare R2, Config Only

`docs/adr/0010-media-platform.md` already claimed "`MEDIA_DISK=s3` (or
R2/Spaces) is the entire migration — no code change." Verified by
reading `config/filesystems.php`'s `s3` disk stub directly: it already
supports a custom `endpoint` and `use_path_style_endpoint`, exactly
what any S3-compatible provider needs, and `config/media.php`'s
`MEDIA_DISK` env var is independent of `FILESYSTEM_DISK` by design.
The claim held — chose R2 over plain S3 for zero egress fees (a real
cost difference for a media-heavy app) and documented the exact env
vars in `docs/DEPLOYMENT.md`. No code touched.

## DNS-Resolution SSRF Hardening

`docs/adr/0007-wordpress-integration-architecture.md` named the gap
precisely: `UrlSafetyValidator::assertSafe()` checked a **literal**
IP address against private/reserved ranges, but a **hostname**
resolving to an internal IP (a malicious or misconfigured DNS record)
sailed through unchecked, since `filter_var($host, FILTER_VALIDATE_IP)`
returns false for anything that isn't already a literal IP, skipping
the whole check.

Added `App\Services\WordPress\Security\DnsResolver` (a thin,
injectable wrapper around `dns_get_record()`) and extended
`assertSafe()`: a hostname now gets resolved and **every** address it
currently points at is checked against the same private/reserved-range
filter the literal-IP path already used. Scoped deliberately to close
exactly the named gap — a hostname pointed at an internal IP — not
full DNS-rebinding (time-of-check/time-of-use) protection, which would
require pinning the resolved IP through to the actual HTTP client
connection. That's real, further hardening, named as future work below,
not silently assumed to be covered.

### A Real Bug Found Building the Test for It

Making this testable without real network calls needed a fake
`DnsResolver` bound globally for the test suite (the same reason
`Http::fake()` is used everywhere) — and building that surfaced a
genuine Pest configuration bug, not a bug in the SSRF fix itself.
`tests/Pest.php` originally declared a standalone
`beforeEach(fn () => fakeDnsResolution())` — this **silently never
fired for any test outside `Pest.php` itself.** Pest's
`BeforeEachRepository` keys hooks by the *exact filename* they were
declared in; a bare top-level `beforeEach()` only applies to tests
declared in that same file, not the whole suite the way `uses()->in()`
scoping might suggest. The fix: chain `.beforeEach()` directly onto
the existing `pest()->extend(TestCase::class)->use(RefreshDatabase::class)`
call, before `.in('Feature')` — `UsesCall` (unlike the standalone
`beforeEach()` function) has its own `beforeEach()` method specifically
designed to combine with `.in()`'s directory targeting.

This was caught, not assumed fixed, by literally instrumenting both
paths with debug logging and confirming zero real DNS calls occurred
after the fix (down from a real, measured 44 real DNS lookups per full
suite run before it) — full investigation narrative in
`docs/ENGINEERING_JOURNAL.md`. The practical consequence of the
original bug: every test touching `UrlSafetyValidator` through a
factory-generated hostname (most of `ContentSyncTest`,
`WordPressConnectionTest`) was making genuine DNS lookups against
random Faker-generated domains — silently working today (those
domains resolve to public IPs or fail to resolve, both currently safe
outcomes), but a real, unnecessary network dependency and a latent
flakiness risk this fix removes entirely.

## Rate Limiting: A Global Backstop, Not Just Four Endpoints

Reviewing the existing rate limiters (`login`, `wordpress-connection`,
`media-upload`, `ai-generation`) surfaced a real gap: every other
authenticated endpoint — Sites/Posts/Media reads, the Dashboard,
GraphQL — had **no rate limit at all**. Added `RateLimiter::for('api',
...)` (120 requests/minute, keyed by authenticated user or IP) and
wired it via Laravel's built-in `$middleware->throttleApi()` onto the
whole `api` middleware group. The four existing limiters stay in place
as tighter, endpoint-specific limits stacked on top — this is a floor
under every request, not a replacement for the stricter ones already
protecting genuinely expensive/sensitive actions.

## HSTS Header

Added `Strict-Transport-Security: max-age=31536000; includeSubDomains`
to `SecureHeaders`. Safe to send unconditionally — browsers only
honor/cache HSTS when it arrives over an actual HTTPS connection, so
it's inert in local HTTP dev and real hardening the moment this
deploys behind HTTPS. `Content-Security-Policy` was evaluated and
**not** added this milestone — a real risk of breaking the Next.js
app without careful per-page verification this milestone's scope
didn't budget for; named as real future work below, not silently
dropped.

## Cross-Domain Auth: Zero Code, All Configuration

`docs/adr/0006-authentication-architecture.md` named this the one real
production blocker Sanctum's cookie-session auth has: Vercel and
Railway's default domains are unrelated, and `SameSite=Lax` cookies
don't cross unrelated domains. Confirmed by reading `config/cors.php`,
`config/sanctum.php`, and `config/session.php` directly: every relevant
value (`FRONTEND_URLS`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`,
`SESSION_SECURE_COOKIE`) is already env-driven with no hardcoded
defaults blocking a fix. The actual fix is a deployment decision, not
a code change: put the frontend and backend on **custom subdomains of
one registrable domain** (`app.yourdomain.com` / `api.yourdomain.com`)
— subdomains of the same registrable domain count as same-site for
`SameSite` cookie purposes, so the existing `Lax` default just works.
Full env var reference in `docs/DEPLOYMENT.md` §4.

## Production Docker Image

`docker/production/php.Dockerfile` — deliberately separate from
`docker/php/Dockerfile`, which `docs/adr/0013-docker-development-
environment.md` explicitly scoped to developer experience (bind
mounts, a tolerant migration race, named volumes seeded at container
start). The production image is self-contained: a `vendor` build stage
installs Composer dependencies with `--no-dev --optimize-autoloader`
so the final `runtime` stage never needs Composer itself; OPcache is
enabled (`docker/production/opcache.ini`, `validate_timestamps=0`);
`docker/production/entrypoint.sh` runs migrations and **fails the
container** if they fail, rather than the dev entrypoint's tolerant
"another container probably already migrated" assumption — appropriate
for a single production instance, wrong for three racing dev
containers.

**Built and smoke-tested locally, not just written and assumed
correct**: `docker build -f docker/production/php.Dockerfile .`
succeeds (222MB final image), and running `php -v`/`php -m` inside the
built image confirms OPcache active and both `pdo_pgsql`/`pdo_sqlite`
loaded correctly.

**No Docker-level `HEALTHCHECK`** — this image only runs PHP-FPM
(FastCGI, no built-in HTTP listener), so a correct check would need a
FastCGI client the base image doesn't ship. Railway's own HTTP health
check, pointed at `/api/v1/health` (which now checks the database *and*
queue, per `docs/adr/0016-observability.md`), is the real signal —
checking through the actual web-facing route the service is judged by.

## Process Supervision: Railway's Own Service Model, Not Hand-Rolled Supervisor

`docs/adr/0009-background-job-platform.md` named "no process
supervision keeps `queue:work` running" as a real gap, deferred here.
Rejected building a Supervisor config inside the container — Railway's
own model (one service per process, independently health-checked and
restarted by the platform) provides the identical real guarantee with
zero extra configuration to maintain. Documented as three Railway
services from the same image (`backend`, `queue`, `scheduler`) in
`docs/DEPLOYMENT.md` §5, matching the exact three-role split
`docker-compose.yml` already uses for local dev.

## Virus Scanning: Evaluated, Documented, Not Built

Named since Milestone 12, deferred here. Not implemented as code —
there's no real scanning service (ClamAV daemon, a cloud provider's
scanning add-on) to build or test against without live infrastructure
this milestone's "deployment-ready, not deployed" scope excludes.
Documented as a concrete recommendation in `docs/DEPLOYMENT.md` rather
than speculative, unverifiable code — the same discipline
`docs/adr/0015-performance-and-scalability.md` applied to declining
premature Redis caching: a real gap named honestly, not papered over
with untested code that only looks like a solution.

## Rejected Alternatives

**MySQL over PostgreSQL.** Considered since it matches the ROADMAP's
literal wording ("MySQL/PostgreSQL") and was `docs/PROJECT.md`'s
tentative placeholder. Rejected in favor of Postgres per the reasoning
above — and moot in practice, since the verification showed the schema
is compatible with either with zero code changes; the choice was
About Railway's native support and JSON/full-text ergonomics, not a
compatibility question.

**A Supervisor/systemd process manager inside the production
container.** Rejected — Railway's own per-service process model gives
the identical guarantee without a config file to maintain; see the
Process Supervision section above.

**A full `Content-Security-Policy` header.** Rejected this milestone
— real risk of breaking the Next.js app's actual script/style loading
without page-by-page verification this milestone didn't budget time
for. HSTS (zero risk, unconditionally safe) was added instead; CSP is
named future work.

**Building speculative virus-scanning code.** Rejected — see the Virus
Scanning section above.

**Attempting a real, live deployment.** Explicitly decided against at
the start of this milestone — see Scope above. Not a limitation
discovered midway; the deliberate framing the whole milestone was
built around.

## Validation

- `php artisan test` — **146/146 passing** (144 unchanged + 2 new:
  the DNS-resolution SSRF test, the `SecureHeaders` test).
- Full suite also run against a real, temporary Postgres 16 container
  — **145/145 passing** (before the two new tests were added), zero
  migration errors.
- `./vendor/bin/pint --test` — clean.
- `docker build -f docker/production/php.Dockerfile .` — succeeds;
  the built image's `php -v`/`php -m` confirm OPcache and both
  Postgres/SQLite PDO drivers load correctly.
- Confirmed via `git log --all -- '**/.env'` that no `.env` file has
  ever been committed to this repository.
- The Pest `beforeEach` bug (see above) was confirmed fixed by direct
  instrumentation — 44 real DNS calls per suite run before the fix, 0
  after — not assumed fixed from reading the diff alone.

## Deferred Work

- **The actual live deployment** — provisioning Vercel/Railway
  accounts, a real domain, and real object storage credentials; see
  `docs/DEPLOYMENT.md` for the complete runbook.
- **A real Sentry DSN** — `docs/adr/0016-observability.md`'s
  integration is code-complete; this milestone's runbook names it as
  a post-deploy verification step.
- **DNS-rebinding (TOCTOU) protection** beyond the resolve-and-check
  this milestone added — pinning the resolved IP through to the actual
  HTTP client connection is real further hardening, not currently
  justified by this app's threat model (a trusted user connecting
  their own WordPress site, not fully adversarial multi-tenant input
  at scale).
- **A full `Content-Security-Policy` header** — needs page-by-page
  verification against the real Next.js app before it's safe to add.
- **Virus scanning on media uploads** — a real, scoped addition once
  a real scanning backend exists to integrate against.
- **Automatic CI-triggered deploys** — `docs/DEPLOYMENT.md`'s manual
  steps are the foundation; wiring GitHub Actions to trigger a deploy
  on merge is a small, real addition once the manual path has been
  run successfully at least once.
