# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-14 — End of Milestone 11 (Background Job & Queue Platform)

**Milestone state.** Milestone 11 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M11.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. **Not yet
committed** — this milestone's own brief requires stopping here for
approval before starting Milestone 12.

**Milestones 8, 9, 10, and 10.1 are already committed and pushed**
(from earlier in this same session). `git status` at the start of this
milestone's work was clean; every file changed since is Milestone
11's own work.

**Content sync is now asynchronous — this changes local testing
behavior.** `POST /sites/{site}/sync` now returns `202 Accepted`
immediately and dispatches `SyncWordPressPostsJob`. **A real
`php artisan queue:work` process must be running** for the job to
actually execute — with `QUEUE_CONNECTION=database` (the local/
production default) and no worker running, a dispatched sync job sits
in the `jobs` table indefinitely and the site stays showing `Syncing`
forever. This is expected, not a bug — `php artisan serve` alone is no
longer sufficient to see a sync complete locally. Start a worker
alongside the other dev servers:
```
php artisan queue:work --queue=default --tries=3 --sleep=1
```
(Pest tests are unaffected — `phpunit.xml` forces
`QUEUE_CONNECTION=sync`, which runs jobs inline within the same
request/test, no worker needed for `php artisan test`.)

**Immediate next step.** Milestone 12 (Storage & Media) is recommended
next per `docs/ROADMAP.md` and this milestone's own report — but is
**explicitly not started**, waiting for approval per the milestone
lifecycle's standing rule.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8 — check `netstat -ano | grep :8000` for stray `php`
  processes if requests start hanging. **New this session:** with a
  queue worker also running, expect *two or three* `php.exe`
  processes locally (`artisan serve` sometimes forks an additional
  process on Windows) — check `tasklist /FI "IMAGENAME eq php.exe"`
  to see all of them before assuming something is stuck.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev`.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, not
  `page.waitForURL()` with its default `waitUntil: 'load'`.
- **New this session, important:** when stopping ad hoc dev
  servers/workers started during verification, identify each one's
  **specific PID** (`netstat -ano | grep LISTENING` for web servers;
  `tasklist /FI "IMAGENAME eq php.exe" /V` for the queue worker, which
  doesn't hold a listening port) and kill only those PIDs. Never
  `taskkill //IM php.exe` or similar — it terminates every process
  with that image name system-wide. This project's own auto-mode
  classifier will correctly deny a broad by-image-name kill; treat
  that denial as the correct behavior, not an obstacle to work around.
- **From Milestone 10.1, worth repeating:** `axe-core` is a real
  transitive dependency (`eslint-plugin-jsx-a11y` needs it), not just
  ad hoc verification tooling — never `rm -rf node_modules/axe-core`
  during cleanup. This session's verification installed only
  `playwright` temporarily and left `axe-core` untouched throughout,
  specifically to avoid repeating that incident.
- The `sync` queue driver (`phpunit.xml`'s test default) does **not**
  retry on failure the way a real worker does — it executes once and
  re-throws past `dispatch()` after calling the job's `failed()`
  callback. Relevant if a future session writes more queue tests; see
  `docs/ENGINEERING_JOURNAL.md`'s 2026-07-14 entry on this.
- Local WordPress connection/sync testing: there is no real WordPress
  server in this environment. `DemoDataSeeder`'s seeded sites carry
  dummy credentials and fake `.example.com` URLs specifically so
  connection/sync actions against seeded data fail gracefully (now via
  a real queue job, landing in `Connection Error`) rather than
  silently looking like they work — expected, not a bug.

**Validation status as of this session.** Frontend: `typecheck`,
`lint`, `build` all pass. Backend: `php artisan test` — 103/103
passing (up from 95). Live verification with a **real** queue worker
process (not `Queue::fake()`, not the test-only `sync` driver):
dispatched a real sync job, watched it get picked up and processed,
watched the site correctly land in `Connection Error` against the
seeded environment's fake domain, and watched System Health's queue
metrics (`1 failed`, `degraded`) update to match — all in a real
browser. `axe-core` audit: zero violations across three checked pages.
See `docs/MILESTONE_REPORT_M11.md`.
