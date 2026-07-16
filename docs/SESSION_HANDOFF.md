# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-16 — End of Milestone 13 (GraphQL Layer)

**Milestone state.** Milestone 13 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M13.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. **Not yet
committed** — this milestone's own stop condition requires stopping
here for approval before starting Milestone 14.

**Milestones 8 through 12 are already committed and pushed** (from
earlier sessions). `git status` at the start of this milestone's work
was clean; every file changed since is Milestone 13's own work.

**New: a GraphQL endpoint exists alongside REST, for the Dashboard
only.** `POST /api/v1/graphql` (`nuwave/lighthouse`) — read-only,
two queries (`dashboardOverview`, `analyticsPreview`), behind the
exact same `auth:sanctum` → `ResolveCurrentWorkspace` middleware every
REST route uses. Nothing else changed: Sites/Posts/Media/WordPress
sync/background jobs are all still plain REST, untouched. If a future
session is tempted to add more GraphQL types, read
`docs/adr/0011-graphql-layer.md`'s Alternatives Considered first —
Sites/Posts were deliberately evaluated and rejected as GraphQL types
this milestone, not simply not-gotten-to.

**Two gotchas worth knowing before touching this again.**

1. **GraphQL enum output fields serialize as their schema NAME, not
   the `@enum(value: ...)` internal value.** If a future schema
   addition uses an enum on an output field, remember: a resolver
   returning `"post-published"` produces `"POST_PUBLISHED"` in the
   actual JSON response — the frontend must translate the wire name
   back to whatever internal value existing code expects (see
   `useDashboardOverview`'s `queryFn` in
   `src/features/dashboard/hooks/use-dashboard-overview.ts` for the
   pattern). This bit once already this milestone (see
   `docs/ENGINEERING_JOURNAL.md`'s 2026-07-16 entry) — genuinely easy
   to get backwards again.
2. **A newly-installed Composer package not appearing in
   `php artisan package:discover`'s output, or `vendor:publish`
   reporting "No publishable resources," almost certainly means a
   stale `bootstrap/cache/services.php`** — this OneDrive-synced-path
   project has hit this exact class of cache staleness for two
   unrelated packages now (first documented Milestone 6, recurred
   Milestone 13). Delete `bootstrap/cache/services.php` and
   `bootstrap/cache/packages.php`, then re-run
   `php artisan package:discover`, before investigating anything else.

**Immediate next step.** Milestone 14 (AI-Assisted Content Generation)
is next per `docs/ROADMAP.md` — but is **explicitly not started**,
waiting for approval per the milestone lifecycle's standing rule.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8; expect two or three `php.exe` processes if a queue
  worker is also running (Milestone 11) — check
  `tasklist /FI "IMAGENAME eq php.exe" /V` before assuming something
  is stuck.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev` — this
  session's verification hit stale/misleading behavior in `npm run
  dev` (a blank-looking chart render that was actually just Fast
  Refresh churn mid-screenshot) that the production build didn't
  reproduce. When something looks visually wrong in dev mode, rule out
  Fast Refresh timing before assuming a real bug — verify against the
  production build to be sure.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, not a `locator.click()`
  + `page.waitForURL()` combination — documented since Milestone 11,
  now also a permanent `docs/ENGINEERING_JOURNAL.md` entry (2026-07-15)
  after recurring once already. Wasn't re-hit this session, but stays
  worth checking first.
- When stopping ad hoc dev servers/workers started during
  verification, identify each one's **specific PID** (`netstat -ano |
  grep LISTENING` for web servers; `tasklist /FI "IMAGENAME eq
  php.exe" /V` for anything without a listening port) and kill only
  those PIDs. Never `taskkill //IM php.exe` or similar.
- `axe-core` is a real transitive dependency (`eslint-plugin-jsx-a11y`
  needs it), not just ad hoc verification tooling — never delete it
  during cleanup. This session's verification installed only
  `playwright` temporarily and left `axe-core` untouched throughout.
- Demo login: `test@example.com` / `password`
  (`backend/database/seeders/DatabaseSeeder.php` + `UserFactory`'s
  default).

**Validation status as of this session.** Backend: `php artisan test`
— **127/127 passing** (up from 120). `./vendor/bin/pint --dirty`:
clean. `php artisan lighthouse:validate-schema`: valid. Frontend:
`typecheck`, `lint`, `build` all pass. Live verification with a real
backend (not a mock): confirmed exactly two GraphQL requests fire on
Dashboard load (down from four separate REST requests), zero legacy
REST dashboard/analytics/system-health requests, real rendered values
throughout, zero console errors. `axe-core`: zero violations on the
GraphQL-backed Dashboard. See `docs/MILESTONE_REPORT_M13.md`.
