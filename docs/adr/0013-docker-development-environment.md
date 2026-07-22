# 0013 — Docker Development Environment

**Status:** Accepted (Milestone 15)

## Decision

Containerize local development with a hand-written Docker Compose setup
— five services (`backend`, `queue`, `scheduler`, `caddy`, `frontend`) plus
an optional, not-started-by-default `redis` — rather than Laravel Sail.
Keep SQLite as the database (no server container). Use Caddy, not Nginx,
as the reverse proxy in front of PHP-FPM. Preserve today's exact port
layout (`:3000` frontend, `:8000` backend) so no existing environment
variable (`FRONTEND_URLS`, `SANCTUM_STATEFUL_DOMAINS`,
`NEXT_PUBLIC_API_URL`) needs to change. This is a **development**
environment only — no production image, orchestration platform, or
deployment concern is in scope (that's Milestone 19).

## Context

**What this milestone is.** Every prior milestone's local setup has been
"install PHP/Composer/Node yourself" (`backend/README.md`, root
`README.md`) — correct for a single-developer portfolio project so far,
but not a reproducible, one-command environment a second developer (or a
future CI runner) could stand up without matching this machine's toolchain
exactly. This milestone is that reproducible environment, scoped
explicitly to developer experience — not the production deployment
Milestone 19 will own.

**Architecture Drift Review.** Reviewed `docs/adr/0004-backend-foundation.md`
(SQLite, no repository layer, envelope-first API), `docs/adr/0009-background-job-platform.md`
(the `database` queue driver, the one registered Scheduler task,
`RefreshSiteMetadataJob`), `docs/PROJECT.md`'s Stack table, and every
`.env.example` (root and `backend/`) before writing any Docker file.
Found: `laravel/sail` already sits in `backend/composer.json`'s
`require-dev` (Laravel's own installer default) but was never actually
initialized — no `docker-compose.yml`, no published stubs, no `SAIL_*`
env vars anywhere in the repo. Genuinely greenfield for this milestone,
not a partial implementation to build on.

## Laravel Sail vs. a Custom Compose Setup

The brief was explicit: evaluate Sail on its merits, don't default to it.
Read Sail's actual published artifacts (`vendor/laravel/sail/stubs/compose.stub`,
`vendor/laravel/sail/runtimes/8.4/Dockerfile`,
`.../supervisord.conf`) rather than going from memory or Sail's marketing
description, since those files are what this project would actually run.

**What Sail's runtime image actually is.** `FROM ubuntu:24.04`, then an
`apt-get install` list covering MySQL/Postgres clients, MongoDB, IMAP,
Redis, Memcached, Swoole, GD, Xdebug, pcov, Playwright's browser
dependencies, and both `pnpm`/`yarn`/`bun` — a general-purpose "any
Laravel app, any stack" image. This project uses none of MongoDB, IMAP,
Redis (yet), Memcached, Swoole, or GD. A larger base image means a slower
`docker compose up --build` and a bigger attack/maintenance surface for
packages nothing here calls.

**What Sail's supervisor actually runs.** Exactly one program:
`php artisan serve --host=0.0.0.0 --port=80`, i.e. Laravel's single-
threaded built-in development server — not PHP-FPM. There is no queue
worker or scheduler entry in `supervisord.conf` at all; both would need
to be hand-added (either as extra `sail artisan queue:work` terminals, or
by editing the Supervisor config) before this milestone's own
requirements (a queue worker, a scheduler process) were met. That's real,
non-trivial custom work either way — Sail doesn't make this milestone's
actual scope smaller.

**Direct conflict with the brief's own requirements.** The brief asks for
PHP-FPM specifically, and a reverse proxy (Nginx or Caddy) in front of it.
Sail's default architecture has neither — it's `artisan serve` with no
proxy, because Sail's one container *is* the web server. Adopting Sail as
specified would mean immediately overriding its own runtime Dockerfile
and Supervisor config to add PHP-FPM and a separate proxy container
anyway, at which point nothing of Sail's actual value (its image, its
process supervision) survives — only its CLI wrapper (`./vendor/bin/sail`
aliasing `docker compose`) would remain, which a two-line shell alias
gets for free without a new Composer dependency's config surface to
understand.

**Frontend is entirely outside Sail's scope regardless.** Sail is a PHP/
Laravel tool; it has no opinion on the Next.js container this milestone
also needs. Choosing Sail solves, at best, a fraction of this milestone's
actual scope while conflicting with two of its explicit requirements.

**Decision: a small, hand-written Compose setup wins on every axis this
milestone actually cares about** — image size (Alpine-based, only the PHP
extensions this specific app requires), architecture fit (PHP-FPM + Caddy
from the start, not retrofitted), and completeness (queue/scheduler are
first-class services, not an afterthought). This isn't rejecting Sail on
principle — it's rejecting it on a direct comparison against what this
milestone actually needs, which is exactly what the brief asked for.

## Reverse Proxy — Caddy vs. Nginx

Both satisfy the brief. Chose **Caddy**: PHP-FPM over FastCGI is a
three-line `Caddyfile`:

```
php_fastcgi backend:9000
```

versus Nginx's equivalent `location ~ \.php$` block (SCRIPT_FILENAME,
fastcgi_param wiring, a separate `try_files` rule) — more lines, more
places to get a path wrong, for identical behavior in this project's
single-app, no-custom-routing-rules case. Caddy's automatic HTTPS/config
simplification isn't the reason (this is HTTP-only local dev,
deliberately — see Environment below) — the reason is strictly "fewer
moving parts to configure and maintain for the one thing this proxy
needs to do." Nginx remains the better choice the moment this project
needs something Caddy doesn't do as simply (complex header rewriting,
multiple backend pools with different rules) — not the case today.

## Volume Strategy — Named Volumes Specifically Where This Project Has Already Been Burned

**`storage/` and `bootstrap/cache/` are named Docker volumes, not part of
the bind mount.** This project's own Engineering Journal documents,
twice (Milestone 6, recurring Milestone 13), a real OneDrive-synced-path
bug: framework cache/build directories under heavy create/delete churn
can silently become OneDrive placeholder reparse points, failing
writability checks plain file operations don't reveal. Bind-mounting
`backend/` into the container (needed so PHP source edits are picked up
without a rebuild) would pass that same OneDrive-backed host path straight
through into the container — Docker doesn't insulate a bind mount from
whatever the host filesystem actually is. Carving `storage/` and
`bootstrap/cache/` out as named volumes (genuine Linux-native storage,
managed entirely by the Docker Desktop VM, never touching the OneDrive
sync client) removes this entire bug class from the containerized path
by construction, not by hoping it doesn't recur.

**`vendor/` and `node_modules/` are also named volumes**, for a different
reason: both are large dependency trees no one edits directly, and
bind-mounting them from an OneDrive-synced host path would be slow (many
small files, real sync overhead) and risks platform-specific binary
mismatches (a Windows-installed Composer/npm package with compiled
extensions vs. the container's Linux runtime). `docker-php-ext-install`/
`npm install` both run at image-build time, and Docker seeds a fresh
named volume from whatever the image already has at that mount point on
first container start — so `composer install`/`npm install` still only
run once per image build, not on every `docker compose up`. The
documented trade-off: after changing `composer.json`/`package.json`, the
volume is stale until `docker compose exec <service> composer install`
(or the npm equivalent) is re-run, or the volume is reset
(`docker compose down -v`) — a well-understood, standard Compose pattern,
not a surprise.

**`.next/` is a named volume too, for the exact same documented reason.**
The Engineering Journal's OneDrive-placeholder incident names `.next/`
directly alongside `bootstrap/cache/` as the two directories that have
actually broken this way. `frontend_next:/app/.next` gets the identical
treatment `backend_storage`/`backend_bootstrap_cache` get, for the
identical reason — this is one bug class, not two, and both of its
previously-hit locations are covered.

**`database/database.sqlite` stays on the bind mount, with one runtime
fix live validation made necessary.** Unlike `storage/`/`bootstrap/cache/`/
`.next/`, this project's documented OneDrive incidents were specifically
about *directories* under rapid create/delete churn, not a single file
receiving writes — and keeping it bind-mounted means the same SQLite
file a non-Docker `php artisan tinker` session already uses is directly
inspectable/editable from the host without an extra `docker compose
exec`, consistent with this project's "SQLite: a single file, zero
setup" philosophy (`backend/README.md`). What this reasoning didn't
anticipate, and live validation caught immediately: a bind-mounted
directory's ownership comes from Docker Desktop's host-to-container UID
mapping (`root:root` on this Windows host), and `www-data` (the PHP-FPM
worker user, in its own group, not `root`) had no write access to it at
all — every session write failed with SQLite's "attempt to write a
readonly database" until the entrypoint script explicitly
`chmod -R o+rwX database` after the bind mount attaches (see Live
Validation Findings below). The single-file-inspectability trade-off
still held; it just wasn't sufficient on its own the way the original
reasoning assumed.

## Networking — Why the Frontend Never Talks to `backend` by Hostname

`src/lib/api-client.ts`'s `API_BASE_URL` defaults to
`http://localhost:8000`, and every data-fetching hook in this app runs
client-side (`"use client"` components calling `useQuery` — see
`docs/PROJECT.md`'s Dashboard Experience section: "every widget that
fetches via `useQuery` is necessarily a Client Component"). There is no
server-side (SSR) call from the Next.js container to the Laravel API
today — every request originates in the **browser**, running on the host
machine, not inside Docker. That means the browser needs `backend`
reachable at a URL it can resolve — `localhost:8000` via Caddy's
published port — not the Docker-internal service name `backend`, which
only resolves *inside* the Compose network. This is why the port layout
is preserved exactly as today's non-Docker setup: zero env var changes,
and the existing `SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000`/
`FRONTEND_URLS=http://localhost:3000` CORS/cookie configuration keeps
working unmodified. A future SSR data-fetching path would need this
revisited (server-side code *would* need the `backend` service-name
hostname) — not a concern this milestone's actual architecture has.

## Redis — Present, Not Wired

Included as a `redis` service under a Compose `profile` (`optional`) so
it does not start with a plain `docker compose up` — `docker compose
--profile optional up` opts in. `CACHE_STORE`, `SESSION_DRIVER`, and
`QUEUE_CONNECTION` all stay on `database` (unchanged from every `.env.example`
default since Milestone 1/11); nothing in this milestone points any of
them at Redis. This satisfies the brief's "may be included only as an
optional container for future milestones. Do not integrate caching yet"
literally: present in the compose file for a future milestone to wire up
without inventing new Compose syntax, inert by default.

## Live Validation Findings — Four Real Bugs, Not Hypothetical Risks

This milestone's own validation (`docker compose build` → `up` →
clean-machine bootstrap → full backend test suite → browser session)
surfaced four genuine defects no amount of config review alone would
have caught, each fixed and confirmed before this milestone was called
done:

1. **`mbstring` failed to compile** — the PHP Dockerfile's `apk add`
   list was missing `oniguruma-dev` (the regex library `mbstring` links
   against). A real, immediate build failure, not a runtime surprise —
   caught on the very first `docker compose build`.
2. **`storage/app` and `storage/framework/*` built read-only** —
   directories copied into the image from a Windows/NTFS build context
   came out without a write bit (NTFS has no Unix permission bits for
   Docker Desktop to preserve). Laravel's session/view-cache writes
   failed with a `tempnam()` error, which the browser reported as a
   misleading CORS failure (the 500 happened before CORS headers could
   attach). Fixed with an explicit `chown www-data:www-data` +
   `chmod -R 775` in the Dockerfile, right after `COPY backend/ ./`.
3. **`database/database.sqlite` was unwritable by `www-data`** — see
   Volume Strategy above. Fixed in the entrypoint script, not the
   Dockerfile, since the bind mount (and therefore its real permissions)
   doesn't exist until the container actually starts.
4. **A mid-login Fast Refresh remount silently dropped the post-login
   redirect, and dev-server responses were taking 100–200+ seconds per
   route.** Two related causes, found together: Next.js has no
   `next.config.ts` (this project is zero-config), so its file watcher
   scans everything under its working directory by default — and the
   frontend container's bind mount (`.:/app`) included the *entire
   repo*, meaning `backend/vendor`'s ~9,800 PHP files were being scanned
   on every check. On top of that, native filesystem change events don't
   propagate reliably from a Windows bind mount across the Docker
   Desktop VM boundary, so the partial/erratic native watch occasionally
   fired a spurious rebuild — one of which remounted the root layout in
   the middle of an in-flight login, orphaning the pending
   `router.replace("/dashboard")` call. Fixed two ways: `WATCHPACK_POLLING=true`
   (deterministic polling instead of unreliable native events) and an
   anonymous-volume shadow (`/app/backend`) hiding `backend/` from the
   frontend container entirely, since it has no reason to watch PHP
   source at all. Route compile times dropped from 100–200s to
   15–20s after the second fix alone — confirming *that* was the
   dominant cost, not general Docker-on-Windows overhead.

**Why this list matters beyond "bugs got fixed."** Every one of these
would have shipped invisibly in a config that was only ever read, never
run — (1) and (2) are fatal (nothing boots or serves a session at all),
(3) breaks every authenticated action silently, and (4) makes the
environment technically functional but practically unusable for the
exact developer-experience goal this milestone exists for. This is the
concrete argument, for this milestone specifically, for why the
Validation stage's "clean-machine `docker compose up`, not just a config
review" requirement was in the brief at all.

## Deferred, Named, Not Forgotten

- ~~**Production images/deployment**~~ **Backend resolved, Milestone
  19** — `docker/production/php.Dockerfile`, multi-stage, no bind
  mounts, no dev dependencies, built and smoke-tested locally. This
  milestone's own dev-shaped Dockerfiles are deliberately unchanged —
  still bind-mount-based, still `next dev`, still scoped to developer
  experience. See
  `docs/adr/0017-cloud-deployment-and-security-hardening.md`.
- ~~**MySQL/PostgreSQL** — the brief is explicit: continue SQLite. The
  production database choice remains Milestone 19's~~ **Resolved,
  Milestone 19** — PostgreSQL, verified against a real instance (every
  migration and the full test suite pass with zero code changes). See
  `docs/adr/0004-backend-foundation.md` and
  `docs/adr/0017-cloud-deployment-and-security-hardening.md`.
- ~~**Redis-backed caching**~~ **Resolved, Milestone 17** — evaluated
  against real measured query timings and deliberately not
  implemented; the container stays present-but-unused. See
  `docs/adr/0015-performance-and-scalability.md`.
- **UID/GID host-permission mapping** — Sail solves a *narrower* version
  of this (a `WWWUSER` build arg matching the host user, so files the
  container creates don't come out root-owned on a Linux host). This
  ADR originally assumed the underlying problem didn't apply on Windows
  at all; live validation proved that wrong within the first `docker
  compose up` — see Live Validation Findings above. What's still
  genuinely deferred is the *general* mechanism (matching an arbitrary
  host UID via a build arg, the way Sail does it): this milestone's
  fixes are targeted `chmod`/`chown` calls solving the two specific
  paths that actually broke (`storage`/`bootstrap/cache` at build time,
  `database/` at container start), not a general host-UID-matching
  scheme. Worth a broader look if a Linux-host contributor joins and
  hits a different path with the same class of problem.
