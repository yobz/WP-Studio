# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-22 — End of Milestone 19 (Cloud Deployment & Security Hardening)

**Milestone state.** Milestone 19 is implemented and validated —
`docs/adr/0017-cloud-deployment-and-security-hardening.md` has the
full reasoning, `docs/DEPLOYMENT.md` is the actual deploy runbook.
**Not yet committed or pushed** — waiting on explicit approval per
this project's standing rule. `docs/ROADMAP.md`, `docs/PROJECT.md`,
and `docs/DEVLOG.md` are already updated to reflect it as complete;
`docs/MILESTONE_REPORT_M19.md` has the full independent review.

**Scope: deployment-ready, not deployed.** This was a deliberate
decision, confirmed with the user before implementation — real
Vercel/Railway accounts, a domain, and object storage credentials need
account access this project's automated work doesn't have. Everything
code/config could cover, this milestone built. Nothing is actually
live anywhere.

**Five things worth knowing before touching this again.**

1. **Production database is PostgreSQL, verified for real.** A
   temporary Postgres 16 container ran every migration and the full
   145-test suite with zero errors and zero code changes — this
   wasn't assumed, it was measured (`docker/production/php.Dockerfile`
   now installs `pdo_pgsql` accordingly). Local dev stays SQLite,
   deliberately.
2. **`tests/Pest.php`'s global `beforeEach()` must stay chained onto
   `pest()->extend(...)->in('Feature')`, never declared standalone.**
   A bare top-level `beforeEach()` in Pest.php silently applies to
   nothing outside that file — Pest's `BeforeEachRepository` keys
   hooks by exact filename, not directory. This cost real debugging
   time this session (see `docs/ENGINEERING_JOURNAL.md`'s 2026-07-22
   entry) — if a future `beforeEach`/`afterEach` addition here seems
   to silently not run, check this first.
3. **`UrlSafetyValidator` now takes a `DnsResolver` constructor
   dependency.** Any test exercising it (most of
   `WordPressConnectionTest`, `ContentSyncTest`) relies on the global
   fake bound in `Pest.php` (`fakeDnsResolution()`, default returns a
   public IP) — call `fakeDnsResolution(['10.0.0.5'])` (or any private
   IP) in a specific test to simulate a hostname resolving somewhere
   unsafe.
4. **`docker/production/php.Dockerfile` is separate from
   `docker/php/Dockerfile`, on purpose.** The dev image (Milestone 15)
   stays bind-mount-based and tolerant of migration races; the
   production image is self-contained, `--no-dev`, OPcache-on, and
   fails the container outright if migration fails. Don't merge them.
5. **`docs/DEPLOYMENT.md` is the actual "how to go live" doc** — read
   it before attempting any real deployment. It covers the
   custom-subdomain strategy Sanctum's cross-domain cookie auth needs
   (§4), which is not obvious from the code alone.

**Immediate next step.** Milestone 20 (Production Release — final
architecture review, readiness audit, deployment checklist, disaster
recovery review, documentation cleanup, dependency audit) is next per
`docs/ROADMAP.md` — the last milestone on the roadmap — but is
**explicitly not started**, waiting for approval. Milestone 19 itself
also still needs explicit commit/push approval.

**Known live gotchas (carried forward, still accurate).**
- Docker (Milestone 15/19): `docker compose up` for dev, `docker
  build -f docker/production/php.Dockerfile .` for the production
  image — both verified working, but Docker Desktop needs to actually
  be running first (`docker info` failing with a `dockerDesktopLinuxEngine`
  pipe error means it isn't started; starting it and waiting ~30-60s
  is enough).
- `composer require`/`composer dump-autoload` can exceed the default
  2-minute tool timeout on this Windows/OneDrive-synced checkout — let
  it run in the background and wait for the notification.
- Local PHP (XAMPP) has `pdo_pgsql`'s DLL present but not enabled by
  default — `php -d extension=php_pdo_pgsql.dll <command>` loads it
  for a single invocation without touching the system `php.ini`, useful
  for any future ad hoc Postgres verification.
- A stack trace pointing entirely inside a dependency's own bundled
  internals, right after a `node_modules` change, is this project's
  recurring stale `.next`/`bootstrap/cache` build-cache pattern — see
  `docs/ENGINEERING_JOURNAL.md`.
- `axe-core` is a real transitive dependency, never delete it during
  cleanup. `playwright` is installed with `--no-save` and uninstalled
  again after ad hoc live verification, every time.
- Never print any part of an API key/credential/DSN/DB password into
  tool output or logs.
- Demo login: `test@example.com` / `password`.

**Validation status as of this session.** Backend: `php artisan test`
— **146/146 passing** (144 unchanged + 2 new: DNS-resolution SSRF,
`SecureHeaders`). Also run in full against a real Postgres 16
container: 145/145 (before the 2 new tests were added). `./vendor/bin/
pint --test` (full-repo) — clean. `docker build -f docker/production/
php.Dockerfile .` — succeeds (222MB image); smoke-tested inside the
built image (`php -v`, `php -m`) — OPcache active, both `pdo_pgsql`/
`pdo_sqlite` loaded. Frontend: untouched this milestone (backend/infra
scope only) — last known state 20/20 passing, typecheck/lint/build
clean (Milestone 17).
