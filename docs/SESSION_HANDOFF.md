# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-14 — End of Milestone 10 (Content Synchronization Platform)

**Milestone state.** Milestone 10 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M10.md` for the full
independent review. `docs/ROADMAP.md` marks it complete (redefined
from its prior "API Completion & Frontend Migration" scope, which is
preserved as Milestone 10.1, not dropped). **Not yet committed** — this
milestone's own brief requires stopping here for approval before
starting the next one.

**M8/M9 are already committed and pushed** (from earlier in this same
session): comment-stripping was applied across the entire M1-M9
codebase in four separate commits, all pushed to `origin/master`.
`git status` at the start of this milestone's work was clean; every
file changed since is Milestone 10's own work.

**Immediate next step.** Either Milestone 10.1 (API Completion &
Frontend Migration — the scope displaced from this slot) or Milestone
11 (Background Jobs & Queues — the direct continuation of this
milestone's synchronous-to-async seam) is next per `docs/ROADMAP.md`,
but **explicitly not started** — waiting for explicit approval per the
milestone lifecycle's standing rule.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8 — check `netstat -ano | grep :8000` for stray `php`
  processes if requests start hanging.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev` — Fast Refresh
  interfered with client-side navigation during Milestone 8's own
  verification (see `docs/ENGINEERING_JOURNAL.md`).
- **New this session:** verifying client-side (App Router) navigation
  with Playwright needs `page.goto()` or a manual URL-polling helper,
  not `page.waitForURL()` with its default `waitUntil: 'load'` — a
  Next.js client-side `<Link>` navigation never fires a `load` event,
  so `waitForURL()` times out even though the navigation genuinely
  succeeded. Worth knowing before writing the next ad hoc verification
  script.
- Local WordPress connection/sync testing: there is no real WordPress
  server in this environment. `DemoDataSeeder`'s seeded sites carry a
  dummy, non-working credential and fake `.example.com` URLs
  specifically so "Verify Connection"/"Sync Content" against seeded
  data fail gracefully rather than silently looking like they work —
  expected, not a bug. This milestone's sync *success* path is
  verified entirely by backend tests (`Http::fake()`); the *failure*
  path was verified live in-browser against the seeded environment's
  genuinely unreachable domain.
- Playwright is not a project dependency — a prior session's ad hoc
  browser verification installed it locally with
  `npm install --no-save playwright` and removed it afterward
  (`node_modules/playwright*`). If a future session wants live browser
  verification again, the same temporary install is the fastest path;
  don't add it to `package.json` unless the project decides to adopt
  automated browser testing for real (that's Milestone 15/18's job).

**Validation status as of this session.** Frontend: `typecheck`,
`lint`, `build` all pass — 13 routes, two new
(`/wordpress/[id]/posts`, `/wordpress/[id]/posts/[postId]`). Backend:
`php artisan test` — 83/83 passing (up from 73). Full sync-failure
flow (real `WordPressConnectionException`, site flipped to
`Connection Error`, error rendered through the existing error-display
path) verified end-to-end in a real, production-mode browser — see
`docs/MILESTONE_REPORT_M10.md`.
