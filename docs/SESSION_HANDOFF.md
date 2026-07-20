# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-20 — End of Milestone 15 (Docker Development Environment)

**Milestone state.** Milestone 15 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M15.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. Not yet
committed — waiting for approval per the milestone lifecycle's standing
rule.

**New: `docker compose up` is a real, working alternative to the
bare-metal setup.** Five services — `backend` (PHP-FPM), `queue`
(`queue:work`), `scheduler` (`schedule:work`), `caddy` (reverse proxy,
publishes `:8000`), `frontend` (`next dev`, publishes `:3000`) — plus an
optional `redis` (`docker compose --profile optional up`, unused by any
app config today). SQLite stays the database. Both READMEs (root,
`backend/`) document the Docker path alongside the unchanged bare-metal
one. Full architecture and every trade-off in
`docs/adr/0013-docker-development-environment.md`.

**Docker Desktop + WSL2 had to be installed this session** — neither was
present on this machine beforehand. If a future session finds Docker
missing again, `docker --version`/`docker info` failing with a `500`
against `dockerDesktopLinuxEngine` almost always means WSL2 itself isn't
installed (`wsl --status`) — install via an elevated `wsl --install`,
restart, then relaunch Docker Desktop.

**Four gotchas worth knowing before touching this again.**

1. **A bind-mounted directory's write permissions on this Windows host
   are not what you'd assume.** `storage/`/`bootstrap/cache/` are named
   volumes specifically so this doesn't apply to them, but
   `database/database.sqlite` is deliberately still bind-mounted (host
   inspectability) — its entrypoint-time `chmod -R o+rwX database` fix
   is load-bearing. If a future change moves database-adjacent files
   around, or a fresh Windows host hits "attempt to write a readonly
   database" again, re-read `docs/ENGINEERING_JOURNAL.md`'s 2026-07-20
   "CORS error that was actually a filesystem permission fault" entry
   before assuming it's a new bug.
2. **A "CORS error" in the browser console is not proof the cause is
   CORS.** The same entry above: a `500` thrown early enough in
   Laravel's pipeline never gets CORS headers attached, and the browser
   reports the missing header as a CORS failure regardless of the real
   cause. `curl -i` the endpoint directly and read the actual response
   before touching `config/cors.php`.
3. **The frontend container deliberately cannot see `backend/`** — an
   anonymous volume (`/app/backend` in `docker-compose.yml`) shadows it
   out. This was a real, measured ~90% dev-server latency fix (100–200s
   → 15–20s per route), not incidental. If the frontend ever
   legitimately needs to read something under `backend/`, that shadow
   is the first thing to reconsider, not remove reflexively.
4. **`docker compose exec backend composer install` (or the `npm`
   equivalent for `frontend`) is a manual step after changing
   `composer.json`/`package.json`** — `vendor`/`node_modules` are named
   volumes seeded once from the image at first container creation, not
   re-synced automatically on every `docker compose up`. `docker compose
   down -v` resets everything if in doubt.

**Immediate next step.** Milestone 16 (Frontend Testing & CI/CD) is next
per `docs/ROADMAP.md` — but is **explicitly not started**, waiting for
approval per the milestone lifecycle's standing rule.

**Known live gotchas (non-Docker / general).**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8, for the bare-metal path only — the Docker path uses real
  PHP-FPM instead, unaffected.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, not a `locator.click()`
  + `page.waitForURL()` combination — documented since Milestone 11.
  Inside Docker specifically, also wait a beat after `page.goto()`
  resolves before interacting — clicking before hydration finishes
  attaching event handlers falls back to native HTML form submission
  (see the Engineering Journal's file-watcher entry for how this first
  surfaced).
- When stopping ad hoc dev servers/workers started during non-Docker
  verification, identify each one's **specific PID** and kill only
  those PIDs. Never `taskkill /IM php.exe` or similar. For Docker
  services, `docker compose down` is the correct, safe equivalent.
- `axe-core` is a real transitive dependency (`eslint-plugin-jsx-a11y`
  needs it), not just ad hoc verification tooling — never delete it
  during cleanup. `playwright` is installed with `--no-save` and
  uninstalled again at the end of verification each time it's used.
- Never print any part of an API key/credential (not even length or a
  prefix) into tool output or logs.
- Demo login: `test@example.com` / `password`
  (`backend/database/seeders/DatabaseSeeder.php` + `UserFactory`'s
  default) — works identically in and out of Docker, same SQLite file.

**Validation status as of this session.** Backend (inside the `backend`
container): `php artisan test` — **142/142 passing**, unchanged from
non-Docker. `./vendor/bin/pint --test` (full-repo, not `--dirty`): 7
pre-existing style issues found in files this milestone didn't touch —
named in `docs/MILESTONE_REPORT_M15.md`'s Risks, not fixed (out of this
milestone's scope). Frontend (inside the `frontend` container):
`typecheck`, `lint`, `build` all pass. Live browser verification: full
login → Dashboard → WordPress → Media → Content → Analytics → Settings
→ AI Assistant Generate flow, all through the containerized stack, zero
console errors, zero `axe-core` violations. See
`docs/MILESTONE_REPORT_M15.md`.
