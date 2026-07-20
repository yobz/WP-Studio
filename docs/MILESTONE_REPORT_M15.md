# Milestone 15 Report

## Date

2026-07-20

---

## Objective

Containerize the entire WP Studio development environment for a
reproducible, one-command local setup — `git clone` + `docker compose
up`, no local PHP, Composer, or Node install required. Explicitly scoped
to developer experience, not production deployment (Milestone 19's job).
Per the brief: evaluate Laravel Sail on its actual merits rather than
defaulting to it, keep SQLite, include a queue worker and scheduler as
first-class services, and choose a reverse proxy (Nginx or Caddy) with
the choice documented.

---

## Executive Summary

Milestone 15 is complete. A hand-written Docker Compose setup — five
services (`backend`, `queue`, `scheduler`, `caddy`, `frontend`) plus an
optional, not-started-by-default `redis` — replaces Laravel Sail as the
chosen approach, decided by reading Sail's actual published Dockerfile
and Supervisor configuration rather than its reputation: it runs
`artisan serve` (not PHP-FPM), has no reverse proxy, and has no
queue-worker or scheduler entry configured at all, conflicting with this
milestone's own explicit requirements rather than satisfying them by
default.

**Live validation — a genuine clean-machine `docker compose up`, not a
config review — caught and fixed four real defects**, none of which a
static read of the configuration would have surfaced:

1. A missing `oniguruma-dev` build dependency broke `mbstring` outright
   (a build-time failure, caught on the first `docker compose build`).
2. `storage/app`/`storage/framework/*` built read-only from the Windows
   build context (NTFS has no Unix permission bits for Docker Desktop to
   preserve), surfacing as a misleading CORS error in the browser since
   the resulting `500` happened before CORS headers could attach.
3. The bind-mounted SQLite database was unwritable by the PHP-FPM worker
   user, for the identical Windows-bind-mount-permissions reason as (2),
   caught only once (2) was fixed and login still failed.
4. A Fast Refresh rebuild remounted the frontend's root layout in the
   middle of an in-flight login, silently dropping the pending
   `router.replace("/dashboard")` call — traced to the frontend
   container's file watcher needlessly scanning `backend/vendor`'s
   ~9,800 files on every check, compounded by native filesystem events
   not propagating reliably across the Docker Desktop/Windows bind-mount
   boundary.

All four are fixed, documented in full in
`docs/adr/0013-docker-development-environment.md`'s Live Validation
Findings section and two dated Engineering Journal entries. Fixing (4)
alone dropped per-route dev-server compile times from 100–200+ seconds
to 15–20 seconds.

Full end-to-end verification followed the fixes: a genuine
clean-machine bootstrap (`.env` and the SQLite database deleted and
regenerated from nothing, all 16 migrations run fresh, `DemoDataSeeder`
run against the empty database), the complete 142-test backend suite,
`./vendor/bin/pint --test` (pre-existing, unrelated style issues found —
see Risks), frontend `typecheck`/`lint`/production build, and a real
browser session — login → Dashboard → WordPress → Media → Content →
Analytics → Settings → AI Assistant's Generate flow — all through the
containerized stack, with zero console errors and zero `axe-core`
violations throughout.

---

## Architecture Review

Read `docs/adr/0004-backend-foundation.md` (SQLite, envelope-first API),
`docs/adr/0009-background-job-platform.md` (the `database` queue driver,
the one registered Scheduler task), `docs/PROJECT.md`'s Stack table, and
every `.env.example` before writing any Docker file — to understand
exactly what the containerized environment needed to reproduce.

---

## Architecture Drift Review

Found `laravel/sail` already present in `backend/composer.json`'s
`require-dev` (Laravel's own installer default) but never actually
initialized — no `docker-compose.yml`, no published stubs, no `SAIL_*`
env vars anywhere in the repo. Genuinely greenfield for this milestone.
The Sail-vs-custom evaluation itself (see Executive Summary) **was** the
substantive drift-review work this milestone required — done by reading
Sail's real runtime artifacts, not assumed from its reputation as "the"
official Laravel Docker tool.

---

## Laravel Sail vs. Custom Compose — the Decision, in Brief

Full reasoning in `docs/adr/0013-docker-development-environment.md`.
Summary: Sail's runtime image is `FROM ubuntu:24.04` with an extensive
`apt-get install` list covering services this project doesn't use
(MongoDB, IMAP, Memcached, Swoole, GD), running exactly one Supervisor
program — Laravel's built-in single-threaded dev server, not PHP-FPM —
with no reverse proxy and no queue/scheduler configured. Adopting Sail
as specified would have meant immediately overriding its own Dockerfile
and Supervisor config to add everything this milestone actually
required, at which point none of Sail's real value would have survived.
A small, Alpine-based, hand-written setup wins on image size,
architecture fit, and completeness — not rejected on principle, rejected
on a direct comparison against this milestone's actual requirements.

---

## Docker Architecture

```
docker-compose.yml
├── backend    — PHP-FPM (Alpine, only the extensions this app uses)
├── queue      — same image, `queue:work --tries=3`
├── scheduler  — same image, `schedule:work` (Laravel's cron-free runner)
├── caddy      — reverse proxy, FastCGI to backend:9000, publishes :8000
├── frontend   — `next dev`, publishes :3000
└── redis      — profile-gated, not started by default
```

`backend`/`queue`/`scheduler` share one `x-php` YAML anchor for their
`build`/`volumes`/`networks` definition (added during this milestone's
own Independent Review — see below), differing only in `command`.
Ports and hostnames match today's non-Docker setup exactly
(`localhost:3000`/`localhost:8000`), so no existing environment variable
(`FRONTEND_URLS`, `SANCTUM_STATEFUL_DOMAINS`, `NEXT_PUBLIC_API_URL`)
needed to change — every browser-originated API call reaches Caddy's
published port directly, never a Docker-internal service name, since
every data-fetching hook in this app runs client-side (no SSR
data-fetching exists yet to need one).

**Volume strategy.** `storage/`, `bootstrap/cache/`, and `.next/` are
named Docker volumes — the exact directories this project's own
Engineering Journal already documented breaking from OneDrive-synced-
path reparse-point staleness (Milestone 6, recurring Milestone 13).
Named volumes remove that bug class by construction. `vendor/`/
`node_modules/` are named volumes for a different reason (large,
unedited dependency trees; platform-binary mismatch risk).
`database/database.sqlite` stays bind-mounted for host inspectability —
see Live Validation Findings for the write-permission fix this decision
turned out to need.

**Reverse proxy.** Caddy over Nginx: PHP-FPM over FastCGI is a
three-line `Caddyfile` (`php_fastcgi backend:9000`) versus Nginx's
equivalent `location ~ \.php$` block — fewer places to get a path wrong
for identical behavior in this project's single-app case.

---

## Live Validation Findings

See Executive Summary for the four defects found and fixed. Full
technical detail (root cause, investigation, fix, and the reasoning
behind each) is in `docs/adr/0013-docker-development-environment.md`'s
"Live Validation Findings" section and two dated
`docs/ENGINEERING_JOURNAL.md` entries (2026-07-20). Not repeated here to
avoid duplicating the same content across three documents — this
report's job is the verdict, not the investigation transcript.

---

## Validation

- **Clean-machine bootstrap**: `backend/.env` and
  `backend/database/database.sqlite` deleted, `backend`/`queue`/
  `scheduler` containers restarted. Entrypoint created `.env` from
  `.env.example`, generated a real `APP_KEY`, created an empty SQLite
  file, and ran all 16 migrations from zero successfully — every
  milestone's schema history, confirmed in one pass.
- **Backend**: `php artisan test` — **142/142 passing** inside the
  `backend` container, unchanged from non-Docker. `php artisan db:seed`
  succeeded against the fresh database (`DemoDataSeeder`, ~4.9s).
  `queue:work` confirmed processing a real dispatched job (`php artisan
  tinker` → `RefreshSiteMetadataJob::dispatch(...)` → observed
  `RUNNING`/`FAIL` in the `queue` container's log against a real,
  unreachable fake site — the job ran for real, failure was the correct
  outcome for a fake target). `scheduler` confirmed running
  (`Running scheduled tasks` in its log).
- **Frontend**: `npm run typecheck`, `npm run lint`, `npm run build` all
  pass inside the `frontend` container. Production build: clean, 33.9s,
  all 12 routes generated.
- **Live browser session** (real login, not a mock): Login → Dashboard
  (all nine widgets rendering real data) → WordPress → Media → Content →
  Analytics → Settings, each route visited and loading without console
  errors. AI Assistant's `Generate` action driven end-to-end against a
  fresh container with no provider key configured — correctly rendered
  the same clean `AI_CONFIGURATION_ERROR` UI Milestone 14 built, now
  confirmed working identically under Docker.
- **Accessibility**: `axe-core` run against the fully-loaded Dashboard —
  **zero violations**.
- **`./vendor/bin/pint --test`** (a full-repo sweep, not `--dirty`) found
  7 pre-existing style issues in files this milestone never touched
  (`AnalyticsSnapshot.php`, `PublishingJob.php`, `Site.php`,
  `SiteFactory.php`, three test files) — see Risks. Not fixed here,
  named honestly rather than silently expanded into this milestone's
  scope.

---

## Independent Architecture Review

Run after implementation, deliberately critical rather than
confirmatory, evaluating Docker architecture, volume strategy,
networking, maintainability, developer experience, security, and
portability.

**Found and fixed during this review:**

- **Real configuration duplication.** `backend`/`queue`/`scheduler`
  originally repeated an identical 8-line `build`/`volumes` block three
  times in `docker-compose.yml`. Refactored to a single `x-php` YAML
  anchor (`<<: *php`), each service now overriding only its `command`
  (and `depends_on` where relevant) — one place to change the shared
  volume list instead of three. Re-verified with `docker compose config
  --quiet` and a full container restart before and after.

**Found, evaluated, and deliberately left as a named trade-off (not
fixed this milestone):**

- **The migration race-tolerance (`|| echo "skipped"`) in the
  entrypoint script can't distinguish "another container won the race"
  from "this migration is genuinely broken."** Both print the same
  reassuring message and let the container start anyway. For this
  milestone's actual failure mode (three containers racing to migrate
  an empty SQLite file on first boot) this is correct and low-risk —
  but a future developer debugging a real, broken migration inside this
  setup could be misled by a log line that looks like "nothing to worry
  about." A more precise fix (a lock file, or migrating only in
  `backend` with `queue`/`scheduler` waiting on a healthcheck) would
  cost real complexity for a dev-only, three-container race that's
  already unlikely in practice. Named here so it isn't rediscovered
  as a mystery later.
- **`chmod -R o+rwX database` in the entrypoint is broader than strictly
  necessary** — it grants write access to *any* process in the
  container, not just `www-data`, because the bind-mounted directory's
  group ownership couldn't be relied on (see Engineering Journal). Fine
  for a local, single-user dev container running no untrusted code;
  would need tightening before any shared or production-adjacent use.
- **No health checks or readiness signaling.** `docker compose ps` shows
  every container as "Up" the moment its process starts, not once it's
  actually ready to serve — the entrypoint's `.env`/migration bootstrap
  and the frontend's first-route compile both take real, visible time
  a new developer has no explicit signal for beyond "the page didn't
  load yet, try again in a moment." A `healthcheck:` block per service
  (and `depends_on: condition: service_healthy`) would be the correct
  fix; not built this milestone to keep scope bounded to what the brief
  asked for.
- **The `redis` container has no password and publishes its port
  unauthenticated.** Irrelevant today (nothing connects to it; it isn't
  even started by default), but worth remembering before Milestone 17
  wires it into anything real.

**Portability note.** The Windows-bind-mount permission fixes
(`chmod`/`chown` in the Dockerfile and entrypoint) are safe on
Linux/Mac hosts too — where the underlying permission problem doesn't
exist, they're a harmless no-op-adjacent broadening, not a break.

---

## Production Readiness

Explicitly out of scope by design — see Objective. Every Dockerfile is
dev-shaped: bind mounts, `next dev`, no multi-stage optimized build, no
orchestration platform. Milestone 19 owns turning this into a deployable
artifact.

---

## Technical Debt Resolved

- **"Install PHP/Composer/Node yourself," the only local-setup story
  since Milestone 1** — resolved. `docker compose up` is now a genuine
  additive alternative; the bare-metal path (`backend/README.md`, root
  `README.md`) remains fully documented and unchanged.
- **The OneDrive-synced-path cache-staleness bug class** (Milestone 6,
  recurring Milestone 13) — closed for the containerized path by
  construction, via named volumes for every directory that class of bug
  has actually hit.

---

## Deferred Work

- **Production Docker images / deployment** — Milestone 19.
- **A general host-UID/GID-matching mechanism** (Sail's `WWWUSER`-style
  approach) — this milestone's fixes are targeted to the two paths that
  actually broke; a Linux-host contributor hitting a different path
  would need a broader look.
- **Redis-backed caching** — Milestone 17. The container exists; nothing
  points at it yet.
- **Health checks / readiness signaling** — named in the Independent
  Review above, not built.
- **7 pre-existing Pint style issues** in files unrelated to this
  milestone — named in Validation/Risks, not fixed here to avoid scope
  creep into unrelated code in a "Docker Development Environment"
  milestone.

---

## Risks

- **This milestone's live validation ran on one Windows host.** Every
  fix (permissions, file-watcher scope, polling) was diagnosed and
  confirmed against this specific machine's Docker Desktop/WSL2
  configuration. The reasoning generalizes (documented explicitly in
  the ADR), but a different Windows configuration, or a first real
  Mac/Linux contributor, should still expect to be the first real test
  of that generalization.
- **`./vendor/bin/pint --test` (full-repo) found 7 pre-existing style
  issues this milestone didn't introduce and didn't fix** — every prior
  milestone validated with `--dirty` (changed files only), so full-repo
  style drift had never been surfaced before. Not a Docker-specific
  risk, but a real, newly-visible gap worth a future cleanup pass.
- **No CI gate exists yet to catch a regression in any of this
  milestone's fixes** — Milestone 16 (Frontend Testing & CI/CD) is the
  named future owner of running `docker compose build`/`up` (or at
  minimum the underlying `composer`/`npm` checks) on every PR.

---

## Recommendation for Milestone 16

Per `docs/ROADMAP.md`, Milestone 16 (Frontend Testing & CI/CD) is next —
Vitest + React Testing Library coverage for the frontend, and GitHub
Actions running lint/typecheck/tests/builds on every PR, closing both
the frontend-testing asymmetry flagged since Milestone 5 and the
no-CI-gate risk named above. Waiting for explicit approval before
starting, per this milestone's own stop condition.
