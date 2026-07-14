# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-14 — End of Milestone 10.1 (API Completion & Frontend Migration)

**Milestone state.** Milestone 10.1 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M10_1.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. **Not yet
committed** — this milestone's own brief requires stopping here for
approval before starting Milestone 11.

**Milestones 8, 9, and 10 are already committed and pushed** (from
earlier in this same session). `git status` at the start of this
milestone's work was clean; every file changed since is Milestone
10.1's own work.

**The frontend no longer has any mock data source.**
`src/services/mock/` was deleted in this milestone — every dashboard
widget now either reads real data or is a deliberately, explicitly
documented placeholder (Quick Actions' two no-target actions; AI
Assistant Preview, pending Milestone 14). If a future session goes
looking for `src/services/mock/dashboard.service.ts`, it's gone on
purpose, not missing by accident.

**Immediate next step.** Milestone 11 (Background Jobs & Queues) is
recommended next — see this milestone's report for the reasoning (it
retires two currently-open named placeholders: `ContentSyncService`'s
synchronous-only operation from Milestone 10, and System Health's
hardcoded `backgroundQueue` metric from this milestone) — but is
**explicitly not started**, waiting for approval per the milestone
lifecycle's standing rule.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8 — check `netstat -ano | grep :8000` for stray `php`
  processes if requests start hanging.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev` — Fast Refresh
  interfered with client-side navigation during Milestone 8's own
  verification.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, not
  `page.waitForURL()` with its default `waitUntil: 'load'` (noted
  after Milestone 10) — a client-side `<Link>` navigation never fires
  a `load` event.
- Playwright and `axe-core` are not project dependencies. This
  session's ad hoc verification installed both locally with
  `npm install --no-save playwright axe-core` and removed them
  afterward. **New this session:** `npm install --no-save` doesn't
  reliably guarantee the packages land in `node_modules` on the first
  attempt in this environment — verify with `ls node_modules/<pkg>`
  before assuming the install succeeded; a second `npm install` call
  resolved it cleanly both times it happened.
- **New this session:** when stopping ad hoc dev servers started
  during verification, kill by the specific PID from
  `netstat -ano | grep LISTENING`, never `taskkill //IM node.exe` or
  similar — a broad by-image-name kill terminates every process with
  that name system-wide, not just the session's own dev servers. (The
  auto-mode classifier will correctly deny broad kills; this is the
  reason why, not just a permission technicality.)
- Local WordPress connection/sync testing: there is no real WordPress
  server in this environment. `DemoDataSeeder`'s seeded sites carry
  dummy credentials and fake `.example.com` URLs specifically so
  connection/sync actions against seeded data fail gracefully rather
  than silently looking like they work — expected, not a bug.

**Validation status as of this session.** Frontend: `typecheck`,
`lint`, `build` all pass — `/settings` grew from a static placeholder
to a real 3.61 kB client-data page. Backend: `php artisan test` —
95/95 passing (up from 83). Live `axe-core` audit against `/dashboard`
and `/settings` — zero violations on both. Every dashboard widget
verified end-to-end in a real, production-mode browser showing real
seeded data (KPI Cards, Recent Activity, Analytics Preview, Recent
Drafts, System Health, WordPress Overview all confirmed rendering
correctly; both real Quick Actions links confirmed navigating
correctly) — see `docs/MILESTONE_REPORT_M10_1.md`.
