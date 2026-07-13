# Engineering Journal

Records the *reasoning* behind non-obvious technical problems — not a
changelog (see `DEVLOG.md` for that) and not a decision record (see
`docs/adr/` for accepted architectural decisions). This is where the
investigation itself is worth preserving, especially when the first
hypothesis was wrong.

---

## Future Backlog

A living, permanently-maintained list of known improvements identified
during implementation but deliberately not acted on in the milestone
that found them — either out of scope, not yet justified by real
usage, or blocked on a future milestone's infrastructure. Updated as
items are added, resolved, or reprioritized; not a chronological log
(see the dated entries below for that).

### High Priority

- **Mobile search loses focus on close** (found Milestone 4.1, still
  open). Closing the inline-expand mobile search returns focus to
  `document.body` instead of the search-toggle icon button. Real
  keyboard/screen-reader-user regression, not cosmetic.
- **`SitePolicy`/`PostPolicy` authorization checks are not eager-load
  safe** (found Milestone 7, by design — see
  [[0005-domain-model]](adr/0005-domain-model.md)'s Performance
  section). `hasMember()` runs a query per call; fine for a single
  `show`/`update` check, a real N+1 the moment Milestone 8 wires
  `can:view` into a list endpoint without eager-loading
  `workspace.users` first. Not a bug yet — no route calls it in a loop
  — but Milestone 8 needs to inherit this awareness, not rediscover it
  under load.

### Medium Priority

- **Sidebar `isActive` uses exact match, not prefix match** (found
  Milestone 4.1, deliberately deferred). `/content/123` doesn't
  highlight the `Content` nav item. Needs a real decision on matching
  rules (e.g. should `/dashboard` prefix-match `/dashboard-settings`?)
  before implementing, not a reflexive `startsWith`.
- **Recent Drafts' deterministic failure is module state, not
  request state** (found Milestone 5). Resets on full page reload but
  not on client-side navigation within the app — fine for demoing the
  pattern once, but anyone navigating away and back mid-session won't
  see the error state repeat. Not worth solving for mock data; revisit
  if the real backend needs a similar demo/staging failure-injection
  mode.
- **Six of nine dashboard widgets remain on the mock service layer**
  (found Milestone 5; KPI Cards migrated Milestone 6, WordPress
  Overview migrated Milestone 7 — see
  [[0005-domain-model]](adr/0005-domain-model.md)). The remaining six
  (Recent Activity, Analytics Preview, Recent Drafts, System Health,
  Quick Actions, AI Assistant Preview) migrate the same way, one at a
  time, as their respective backend domains get real logic.
- **Sites/Posts index endpoints have no pagination** (found Milestone
  7, by design — see the ADR's Performance section). Fine at today's
  seeded volume; a real gap once a workspace has hundreds of posts.
  Needs a real page-size decision before implementing, not a reflexive
  default.
- **Workspace deletion has no dedicated flow** (found Milestone 7, by
  design). `Workspace::delete()` today hard-deletes and cascades to
  every site/post — correct as a database constraint, but a real
  product needs a deliberate tenant-deletion flow (confirmation, data
  export, grace period) before this is ever exposed through an API
  endpoint. No such endpoint exists yet, so not urgent — but flagged
  before one gets added casually.

### Low Priority

- **`src/styles/` exists but is empty and undocumented** (found
  Milestone 4). Either populate it with a real purpose or remove it —
  an empty, unexplained directory is a small but real ambiguity for
  the next person navigating the codebase.
- **`components.json`'s `iconLibrary`/style presets are hand-picked
  and undocumented as to why** (found Milestone 4). Low risk, but a
  future contributor changing them wouldn't know what would break.
- **Root `README.md` is still Create Next App's default boilerplate**
  (found Milestone 6, noticed while writing `backend/README.md`). Not
  this milestone's scope to fix, but worth doing before the project is
  shown to anyone external — a portfolio project's own top-level
  README being unedited default text undercuts the "production
  software" framing everywhere else.
- **Local development runs on SQLite, not a server database** (found
  Milestone 6, by design — see the ADR's Trade-offs). Fine for this
  milestone's architecture-only scope; a real deployment target should
  make (and document) a deliberate MySQL/PostgreSQL choice before
  Milestone 15 (Production Release), not inherit SQLite by default.
  **Update, Milestone 7:** confirmed this choice has a real,
  non-hypothetical cost — SQLite's lack of foreign-key
  auto-indexing directly caused the two missing-index findings this
  milestone's self-review caught (see
  [[0005-domain-model]](adr/0005-domain-model.md)). Worth re-auditing
  every migration's indexes specifically when a production database
  choice is finally made, in case MySQL/Postgres-specific behavior cuts
  the other way on something else.

### Deferred Priority

- **AI Assistant Preview has no real backend** (by design, Milestone
  5). Integration point documented inline in
  `src/features/dashboard/components/ai-assistant-preview.tsx` and in
  [[0003-dashboard-data-architecture]](adr/0003-dashboard-data-architecture.md);
  deferred to the milestone that adds AI integration, not tracked as a
  bug.
- **No "AI Jobs" table or model exists** (by design, Milestone 7 —
  see [[0005-domain-model]](adr/0005-domain-model.md)'s Domain Model
  section). Deliberately not guessed at without a real AI provider
  integration to design the schema against — unlike `PublishingJob`,
  which got built this milestone because its shape is generic and
  well-understood.
- **Every backend API route is unauthenticated** (by design, Milestone
  6 — see [[0004-backend-foundation]](adr/0004-backend-foundation.md)).
  Milestone 8 (Authentication) adds Sanctum; `config/cors.php`'s
  `supports_credentials` and the `sanctum/csrf-cookie` CORS path are
  already prepared for it. **Update, Milestone 7:** `SitePolicy`/
  `PostPolicy` now contain real, tested authorization logic
  (`PolicyTest.php`) ready for Milestone 8 to wire in — see the High
  Priority item above for the one thing that logic needs (eager
  loading) before it's wired into a list endpoint.
- **No repository layer in the backend** (by design, Milestones 6–7 —
  nothing to abstract yet; revisit only if a real second data source
  or complex query-composition need appears).
- **No real analytics/events schema** (by design — see
  [[0005-domain-model]](adr/0005-domain-model.md)). **Update, Milestone
  7:** partially addressed — `AnalyticsSnapshot` (one row per site per
  day) now exists and backs a real Dashboard trend calculation, but
  it's a periodic rollup, not event-level tracking (page views,
  sessions, referrers). Still tracked as the future Analytics
  milestone's job; `AnalyticsSnapshot` was designed as a plausible
  aggregation target for that milestone, not a replacement for it.
- **Analytics, AI, and Settings API domains are still placeholder
  endpoints** (Sites and Posts became real CRUD in Milestone 7 — see
  [[0005-domain-model]](adr/0005-domain-model.md)). Each becomes real
  in its own future milestone (Analytics, AI, Settings respectively) —
  see `docs/ROADMAP.md`.

---

## Interview Highlights

A living, permanently-maintained collection of engineering decisions
worth talking through directly in an interview — organized by
milestone, growing each time a milestone produces a decision worth
keeping. Older entries aren't pruned as newer ones are added.

### Milestone 4.1 (Product Shell Hardening)

Five engineering decisions, written to be talked through directly.

**1. Nested `<main>` landmarks — a bug static tooling can't see.**
*Problem:* `DashboardLayout` rendered `<main>` inside `SidebarInset`,
itself `<main>` — two landmarks, one nested in the other, invalid per
the HTML spec. *Chosen solution:* change the inner wrapper to a plain
`<div>`; `SidebarInset` already owns the semantic landmark. *Trade-offs:*
essentially none — a one-line, purely additive-safety fix with zero
behavioral or visual change (verified via before/after screenshot).
*Why this approach:* the alternative (removing `SidebarInset`'s `<main>`
and keeping the app's own) would fight the vendor primitive's own
contract for no benefit; deferring to the primitive's landmark and
removing the redundant one is the smaller, more correct diff.

**2. Choosing a mobile search pattern from four real options.**
*Problem:* search was `hidden` entirely below `sm` — not degraded, just
gone. The milestone brief offered four patterns: icon+expand, `Sheet`,
`Dialog`, `Popover`. *Chosen solution:* inline expand — a search icon
toggles the header row to a full-width input + close button, one
boolean `useState`, zero new dependencies. *Trade-offs:* less "dedicated
screen" polish than a `Sheet` would give; accepted, because search here
is presentational-only today (no results UI to give a dedicated screen
real weight to). *Why this approach:* `Sheet`/`Dialog` are built for
content needing a backdrop/dedicated space — using one for a single
input is more machinery than the job needs, and we'd be repurposing the
`Sheet` already pulled in for the sidebar's mobile drawer rather than
reaching for the simplest tool; `Popover` is anchored/floating by
design, awkward for something that wants full header width.

**3. Disabling a link needs two layers, not one.** *Problem:* the
sidebar's "Help & Support" link had no real destination but was fully
clickable. *Chosen solution:* `aria-disabled="true"` (which triggers
the existing `sidebarMenuButtonVariants`' built-in
`aria-disabled:pointer-events-none aria-disabled:opacity-50` styling
for free) plus an `onClick` `preventDefault()`. *Trade-offs:* two
mechanisms for one outcome looks redundant at a glance. *Why this
approach:* it isn't redundant — verified independently that
`pointer-events: none` blocks mouse clicks (Playwright's own click
simulation correctly failed to land on the element) but does **not**
block keyboard-triggered `Enter` activation, which only `preventDefault()`
stops. Confirmed both paths separately rather than assuming one implies
the other.

**4. `axe-core`'s rule-tag scope had a real blind spot — and widening
it immediately found real bugs.** *Problem:* the Milestone 4 report
predicted, as a *risk*, that scoping every audit to
`wcag2a`/`wcag2aa`/`wcag21a`/`wcag21aa` tags could hide `best-practice`-
tagged checks like `landmark-one-main`. *Chosen solution:* widened this
milestone's audits to include `best-practice` tags. *Outcome, not just
a decision:* it immediately surfaced two previously-invisible
violations — `heading-order` and `page-has-heading-one` — neither of
which were even hypothesized in the M4 report. *Why this matters for an
interview answer:* it's a concrete example of a predicted risk being
validated by acting on it, not just noted and left; the fix (see next
item) came directly from following through on a recommendation rather
than treating "0 violations" as a finished state.

**5. Making a shared component's heading level a prop instead of a
constant.** *Problem:* `EmptyState` hardcoded `<h3>`, which was wrong
in two different ways depending on context — skips a level after
`PageHeader`'s `<h1>` (six pages), and leaves zero `<h1>`s where
`EmptyState` is the only heading (404, error boundary). *Chosen
solution:* `titleAs?: "h1" | "h2" | "h3"`, default `"h2"` (correct for
the common case), with the two exceptions passing `"h1"` explicitly.
*Trade-offs:* a slightly wider public prop surface on a shared
component. *Why this approach over alternatives:* considered inferring
the right level automatically (e.g. via React Context reporting whether
a `PageHeader` is an ancestor) — rejected as solving a four-call-site
problem with infrastructure; an explicit prop is more code to type
once, but zero magic to debug later.

### Milestone 7 (Domain & Data Platform)

**1. Reasoning through domain ownership before writing a migration.**
*Problem:* Milestone 6 shipped `sites`/`posts` with no tenant concept
at all — a flat, ownerless list. *Chosen solution:* designed the full
entity graph (`Workspace` → `Site`/`AnalyticsSnapshot`;
`Workspace` ↔ `User` many-to-many with a role) on paper before
`Schema::create` was written once. *Trade-offs:* slower to first line
of migration code. *Why this approach:* it's what surfaced the pivot
table's naming collision (Laravel's alphabetical `user_workspace`
convention vs. the intended `workspace_user`) as a design question
answered deliberately, not a runtime error discovered by accident —
and why two missing indexes (below) got caught in review rather than
in production.

**2. A denormalized column that became a real historical model.**
*Problem:* Milestone 6's Dashboard KPI trend was permanently omitted
— `sites.monthly_visitors` was one mutable number with no history to
compare against. *Chosen solution:* replaced it with
`AnalyticsSnapshot` (one row per site per day) and a real 14-day vs.
14-day period-over-period trend calculation in `DashboardService`.
*Trade-offs:* a new table and a seeder that now generates 28 days of
history per site instead of one static number. *Why this approach:*
this is the smallest schema that makes the trend real without
pretending to solve full event-level analytics (explicitly a future
milestone's job) — and it closes a gap flagged in two previous
milestone reviews, verified live in a browser (the frontend now
renders "+105.8% vs. prior 14 days," not an omitted field).

**3. Writing and testing authorization logic with nothing to enforce
it yet.** *Problem:* real `Workspace`/`User` membership now exists,
but no route has an authenticated user to check it against.
*Chosen solution:* wrote real `SitePolicy`/`PostPolicy` logic (owner/
admin/member role checks) and tested it directly against the policy
classes (`PolicyTest.php`), without adding a single `authorize()` call
to any controller. *Trade-offs:* the logic sits unused for at least
one more milestone. *Why this approach:* the alternative — writing the
policies *and* wiring them in Milestone 8 at the same time auth
lands — means the first time the logic runs for real is also the
first time it's tested, under time pressure to ship login. Proving it
correct now, in isolation, means Milestone 8 wires in already-verified
logic instead of debugging authorization and authentication
simultaneously.

**4. Catching a SQLite-specific indexing gap self-review would have
missed on MySQL.** *Problem:* `sites.workspace_id` and
`workspace_user.user_id` had foreign key constraints but no
index. *Chosen solution:* added explicit indexes for both,
documented why. *Trade-offs:* two extra `$table->index()` calls.
*Why this matters for an interview answer:* MySQL/InnoDB silently
indexes a column the moment a foreign key constraint touches it;
SQLite doesn't. A schema developed and self-reviewed with only MySQL
experience in mind could ship this gap invisibly — it doesn't error,
it just does a full table scan, and that's indistinguishable from
correct at low seed-data volume. Catching it required actively
reasoning about the *specific* database being used, not pattern-
matching against general "did I add foreign keys" instinct.

**5. Deliberately not building the AI Jobs table.** *Problem:* the
milestone brief named "AI Jobs" as a domain concept the platform
should understand. *Chosen solution:* documented it in the ADR's
domain model and Future Backlog; built no table, no model. *Trade-
offs:* "Domain & Data Platform" ships without one of its named
concepts having any schema at all. *Why this approach:* contrasted
directly against `PublishingJob`, which *did* get built this same
milestone — the difference is that "an async operation with a status
and a timestamp" is a well-understood, generic shape, while "an AI
job" depends entirely on which provider, which model, and what a
prompt/response/cost record needs to look like — none of which is
knowable without a real integration to design against. Building a
guessed schema now would very likely mean a breaking migration later;
naming the gap explicitly is more honest than filling it with a
placeholder that looks more finished than it is.

---

## Resume Highlights

A living, permanently-maintained collection of ATS-friendly resume
bullets, based only on work actually completed — organized by
milestone, growing over time. Not exaggerated; every bullet below maps
to real, shipped, verified work.

### Milestone 7 (Domain & Data Platform)

- Designed and implemented a multi-tenant domain model (Workspace,
  Site, Post, AnalyticsSnapshot, PublishingJob) in Laravel, including
  a many-to-many workspace membership system with role-based
  permissions.
- Built full CRUD REST APIs for two core resources with centralized
  validation (Form Requests), authorization policies, and a
  consistent JSON response envelope, backed by 38 passing Pest tests
  covering HTTP behavior, model relationships, validation rules, and
  authorization logic.
- Replaced a denormalized metrics column with a proper historical
  snapshot table and implemented real period-over-period trend
  calculation, closing a product gap (missing KPI trends) flagged
  across two prior engineering reviews.
- Identified and fixed a database-engine-specific indexing gap
  (SQLite does not auto-index foreign key columns, unlike MySQL)
  during self-review, preventing a silent full-table-scan performance
  issue before it reached any real data volume.
- Authored a comprehensive architecture decision record covering
  entity relationships, indexing strategy, soft-delete policy, and
  explicitly-deferred schema decisions with documented rationale for
  each.

---

## 2026-07-13 — Laravel's pivot-table naming convention is alphabetical, not "however you name the migration"

**Problem.** `Workspace::users()` and `User::workspaces()` both called
plain `belongsToMany()`, and the app crashed on the very first write
to the relationship: `SQLSTATE[HY000]: General error: 1 no such
table: user_workspace` — a table that was never created; the actual
migration created `workspace_user`.

**Investigation.** Laravel's `belongsToMany()` derives a default pivot
table name by taking both model names, sorting them alphabetically,
and joining with an underscore — `User` and `Workspace` sort to
`User`, `Workspace`, giving `user_workspace`. The migration had been
named `create_workspace_user_table` on the (reasonable-sounding, but
wrong) assumption that "workspace owns the membership concept, so its
name goes first" — a naming choice that reads naturally to a human and
is irrelevant to Eloquent's actual convention.

**Decision.** Rather than rename the migration/table to match the
convention, passed the table name explicitly to both `belongsToMany()`
calls: `belongsToMany(User::class, 'workspace_user')` and the reverse.
`workspace_user` is the more readable name in migration files,
schema diagrams, and raw SQL — worth the two extra explicit arguments
rather than bending the schema to match a naming convention.

**Outcome.** Fixed on the first retry once the actual failing query
(visible in the exception message) was read carefully — the error
message named the exact table Eloquent was looking for, which is what
made the mismatch obvious rather than requiring a debugger.

**Lessons learned.** A framework convention that "just works" when you
follow it by accident stops working the moment your naming instinct
disagrees with the framework's — and the fix is almost never to fight
the convention project-wide, it's to be explicit at the two or three
call sites that need to differ from it.

---

## 2026-07-13 — An off-by-one date range made a passing-looking test wrong

**Problem.** A test asserting `DashboardService`'s new trend
calculation (100% increase: 1,400 visitors → 2,800) failed with
`115.4` instead of `100.0` — the *current*-period number was correct,
only the *previous*-period comparison was off.

**Investigation.** The service defines its two 14-day windows as
`[today-13, today]` (current) and `[today-27, today-14]` (previous) —
contiguous, no gap, 28 days total ending today. The test's fixture
data used `range(15, 28)` for the "previous" window, intending "the 14
days before the current window" but actually seeding `today-15`
through `today-28` — offset by exactly one day from what the service
queries (`today-14` through `today-27`). The result: only 13 of the
service's 14 expected previous-window days had matching fixture data
(`today-14` was never seeded; `today-28` was seeded but fell outside
the service's window entirely) — a real number, just computed from
incomplete data, which is why the test *ran* successfully and produced
a plausible-looking (if wrong) result rather than an obvious error.

**Decision.** Fixed the fixture's range to `range(14, 27)`, matching
the service's actual window boundaries exactly.

**Outcome.** Re-ran: exact expected trend (`100.0`) on the first try
after the fix, confirming the *service's* math was correct all along —
the bug was entirely in the test's own fixture construction.

**Lessons learned.** A failing assertion with a plausible-but-wrong
number (`115.4` instead of `100.0`) is a different, easier-to-fix-in-
the-wrong-place kind of failure than a crash — it's tempting to adjust
the *service* to match what the test expected, which would have been
exactly backwards here (the service was right; the test's date math
was off by one day). Worth verifying which side of an assertion is
actually wrong before "fixing" either one.

---

## 2026-07-13 — Closing the four verified M5 findings

Four findings from `docs/MILESTONE_REPORT_M5.md`'s independent review,
addressed before Milestone 6 (Backend Foundation) began, per that
report's own recommendation ("approve, fix forward" — none were
architectural). Each is a separate decision; grouped here since all
four were resolved in the same pass.

### Restoring a server-safe `<h1>`

**Problem.** `WelcomeSection`'s greeting `<h1>` only rendered once a
`mounted` guard flipped true — verified in the M5 review to be
genuinely absent from the production static HTML
(`.next/server/app/dashboard.html`), not just a dev-mode artifact.
`page-has-heading-one` failed when `axe-core` was run immediately after
`DOMContentLoaded`, before the mount effect had run.

**Investigation.** The original `mounted`-guard existed to solve a
real problem (a build-time-frozen `Date.now()` going stale for later
visitors, since this route is statically generated) but the *chosen
mechanism* — swapping the whole `<h1>` element for a `Skeleton` until
mount — solved that problem by introducing a new one: the page's only
heading became conditionally absent. The two concerns (avoid a stale
greeting; always have an `<h1>`) don't actually require the same fix.

**Decision.** Keep the `<h1>` element unconditionally rendered, with a
static, time-agnostic default (`"Welcome back"`) that's identical on
the server-rendered HTML and the client's first paint — only the *text
inside* the already-present heading swaps to the time-aware greeting
post-mount. This isn't a hydration mismatch (the DOM node and its
initial text are identical in both renders); it's a normal client-side
state update after the fact, the same category of change as any other
`useState` update.

**Outcome.** `.next/server/app/dashboard.html` now contains exactly
one `<h1>` ("Welcome back") in every static build, verified by
re-running the same production-artifact grep the M5 review used.

**Lessons learned.** "This value needs client-time evaluation" and
"this element should exist unconditionally" are two different
requirements that had been solved with one mechanism (conditional
rendering) when only the first one actually needed it. Splitting them —
always render the element, defer only the *value* — is the general
fix for this shape of problem, not specific to greetings.

### Native `disabled`, not `aria-disabled`, for the workspace selector

**Problem.** `WelcomeSection`'s "My Workspace" placeholder button used
`aria-disabled="true"` + an `onClick` `preventDefault()` — the pattern
established in Milestone 4.1 for the sidebar's "Help & Support"
*link*. The M5 review found it left the button keyboard-focusable
(confirmed via a live tab-order trace), unlike `QuickActions`' buttons
(built the same milestone), which correctly used native `disabled` and
were properly skipped.

**Investigation.** The `aria-disabled` + `preventDefault()` pattern
exists specifically for elements that *can't* take a native `disabled`
attribute — links (`<a>`). A plain `<button type="button">` has no
such restriction, and `preventDefault()` on a button click has no
default browser action to prevent in the first place (unlike a link's
navigation, or a `type="submit"` button's form submission) — so it was
functionally a no-op, not a working guard.

**Decision.** Switched to native `disabled`, matching `QuickActions`'
own reasoning (documented inline there: "genuinely `disabled`... because
there is no destination or handler to guard against").

**Outcome.** The button is now correctly excluded from the tab order —
re-verified via the same keyboard-focus check the M5 review used.

**Lessons learned.** A correct pattern (the two-layer link-disabling
approach) applied to a superficially similar but structurally
different element (a button, not a link) stops being correct. Worth
checking *why* a pattern exists, not just that a fix "worked" before,
when reusing it somewhere new.

### Removing the dead `getQuickActions()` export

**Problem.** `dashboard.service.ts` exported `getQuickActions()`;
nothing called it. `QuickActions` read `mockQuickActions` fixture data
directly, bypassing the service/hook layer every other widget used.

**Decision.** Considered both options the M5 review offered: wire
`QuickActions` through a `useQuickActions()` hook for consistency with
its siblings, or delete the unused export. Chose deletion.
`QuickActions` renders genuinely static content — four disabled
placeholder cards with no async state, no loading/error/empty
variation possible. Adding a query hook would convert it from a
Server Component to a Client Component (`useQuery` requires
`"use client"`) purely for stylistic uniformity with widgets that
*do* have real async data — the opposite of `CODING_STANDARDS.md`'s
"Client Components only when interactivity... require it."

**Outcome.** `getQuickActions()` removed; `QuickActions` unchanged
(still a Server Component, still reads `mockQuickActions` directly).
Zero dead exports remain in `dashboard.service.ts` (re-verified via
grep across `src/` for every exported function name).

**Lessons learned.** "Every widget should look the same" and "every
widget should use the minimum machinery its actual behavior needs" are
in tension exactly once in this codebase (`QuickActions`), and they
point in different directions here — chose the latter, and documented
why, so the inconsistency reads as a deliberate choice to the next
person who notices it, not an oversight.

### Wiring the notification store into real (mock) data

**Problem.** `useNotificationStore`'s `setCount` had zero call sites —
the header's notification badge and "Mark all as read" flow were
permanently unreachable, despite the store's own doc comment claiming
"the dashboard's activity query... sets the count once data loads."

**Decision.** Wired it for real: `RecentActivity` now calls
`setCount(activity.length)` in a `useEffect` whenever its query's
`data` changes (TanStack Query v5 removed `onSuccess` from `useQuery`,
so an effect watching `data` is the supported replacement). The
header's own copy already reads "you have N updates from your
dashboard activity" — activity count *is* the notification count, no
new concept needed.

**Outcome.** The badge now reflects real (mock) data on every load;
"Mark all as read" genuinely clears it. Documented, not hidden: this
is not real read/unread persistence (there's no backend to remember a
dismissal), so a later activity refetch resets the count — acceptable
for a mock-data milestone, called out explicitly in both the store's
doc comment and the component's.

**Lessons learned.** "Wire it to something real" doesn't have to mean
inventing new backend-shaped functionality — the simplest honest
mapping (badge count = activity count, exactly what the copy already
says) was sufficient and required no new mock data, no new types, and
no speculation about what a future notifications feature will actually
need.

---

## 2026-07-13 — A OneDrive-synced project path breaks framework caches, twice now

**Problem.** `php artisan package:discover` (triggered automatically
by `composer require`) failed: "The `bootstrap/cache` directory must
be present and writable" — despite the directory visibly existing,
being writable via plain shell commands (`echo`, `cat`, `rm`), and
`icacls` showing the current user with full control.

**Investigation.** `icacls` also showed something the "present and
writable" framing didn't explain: a `DENY` ACL entry for "Delete
Child" (`DC`) on `Everyone`, and the directory's Windows attributes
included `ReparsePoint` — a marker OneDrive uses for its
on-demand/placeholder file sync mechanism, not a plain local
directory. PHP's own `is_writable()`-style checks (which
`PackageManifest` uses internally) apparently resolve differently
against a reparse-point directory than POSIX shell utilities do
through Git Bash's translation layer — enough of a mismatch that the
directory was writable by one measure and not by Laravel's.

**Decision.** `rm -rf bootstrap/cache && mkdir bootstrap/cache`
(recreating the `.gitignore` inside it afterward) — replacing the
OneDrive-managed placeholder directory with a plain local one.

**Outcome.** Fixed immediately; `package:discover` and every
subsequent `artisan` command worked normally afterward. Recognized
this as the *same* fix already documented in this journal's Milestone
3 entries for `.next`'s `EINVAL: invalid argument, readlink` failure —
that was resolved identically (`rm -rf .next`), also attributed to
OneDrive sync interference, also on this exact project path.

**Lessons learned.** One occurrence of a framework-cache directory
misbehaving on a synced path could be a fluke; two occurrences across
two unrelated toolchains (Next.js's build cache, Laravel's bootstrap
cache) on the same path is a pattern, not a coincidence — developing
this project from `OneDrive\Desktop\wp-studio` carries a standing,
repeatable risk that any framework's local cache/compiled-artifact
directory can silently become a OneDrive placeholder and start failing
writability checks that plain file operations don't reveal. The fix is
cheap and now documented (`rm -rf <cache-dir>`, recreate), so the next
occurrence — in whatever toolchain finds it third — should cost a
lookup here, not a fresh investigation.

---

## 2026-07-13 — A static route with a clock-dependent greeting

**Problem.** `WelcomeSection` needs to show "Good morning/afternoon/
evening" and today's date — both are functions of `Date.now()`. The
dashboard route has no per-user data yet, so Next.js statically
generates it at build time. A greeting computed during that build
would be frozen: someone visiting weeks later would see whatever time
of day the build happened to run at, not their own.

**Investigation.** Considered forcing the route to render dynamically
per-request (`export const dynamic = "force-dynamic"`), which would
give every widget a fresh server clock — but that discards static
generation for eight widgets that have no per-request variance at all,
to fix a problem in one. Recalled the project already solved the
structurally identical problem for `ThemeToggle` (Milestone 4): a
value that must reflect the *client's* runtime state, not the
server's build/render state, rendered via a `mounted` guard
(`useState(false)` + `useEffect(() => setMounted(true))`) so the
initial paint is a neutral placeholder and the real value appears only
after hydration, avoiding a server/client text mismatch entirely.

**Decision.** Applied the same `mounted`-guard pattern to
`WelcomeSection`: render `Skeleton` placeholders for the greeting/date
until mounted, then compute them from the browser's own clock.

**Outcome.** Confirmed via Playwright across four viewport sizes —
zero console errors (in particular, no React hydration-mismatch
warning), and the rendered greeting matches the current local time in
every screenshot.

**Lessons learned.** "Prefer Server Components" and "this value must
reflect the visitor's actual runtime state" are two different axes,
not one — a value can need client-time evaluation without needing any
user interactivity. Recognizing this as *the same class of problem*
already solved elsewhere in the codebase (rather than treating it as
novel) avoided re-deriving the fix from scratch.

**Backlog items.** None — fully resolved within this milestone.

---

## 2026-07-13 — Demonstrating an Error state without flakiness

**Problem.** The milestone brief requires visibly demonstrating
TanStack Query's retry behavior and Error UI for Recent Drafts. A
mock service that always succeeds never exercises that code path at
all; a service that fails with `Math.random()` exercises it
unpredictably — a demo, code review, or screenshot pass might land on
a run where the failure never appears (or one where every widget
fails at once).

**Investigation.** The actual requirement wasn't "sometimes fail" but
"fail in a way a reviewer can reliably see, then recover." A
module-scoped counter (`draftAttempts` in `dashboard.service.ts`) that
fails calls 1 and 2, then succeeds from call 3 onward, is fully
deterministic per browser session. Paired that with a `retry: 1`
override on `useRecentDrafts` (global default is `retry: 2`) so the
automatic call + 1 retry (2 total attempts) are both consumed by the
guaranteed failures — the Error UI is guaranteed to render before any
manual action, rather than possibly auto-recovering silently on the
second automatic attempt.

**Decision.** Deterministic counter-based failure + per-query `retry`
override, not `Math.random()`.

**Outcome.** Verified with a dedicated Playwright script: on first
load, "Couldn't load drafts" is visible; clicking "Try again" (the
3rd call) shows the real draft list. Both assertions passed on the
first run.

**Lessons learned.** When a spec asks you to "demonstrate" an async
state, treat reliability of the demonstration itself as a requirement,
not just correctness of the underlying logic — a technically-correct
random failure that a viewer never happens to see has failed the
actual goal just as much as a bug would.

**Backlog items.** Failure state is module state, not request state
(see Future Backlog, Medium Priority) — acceptable for a mock-data
demo, revisit if a similar deterministic-failure pattern is ever
needed against a real backend.

---

## 2026-07-11 — Investigations backing the Interview Highlights above

Full investigation detail for two of the five decisions above, in the
Problem/Investigation/Decision/Outcome/Lessons format used elsewhere in
this journal.

### Nested `<main>` and the value of an independent review pass

**Problem.** See Interview Highlights #1.

**Investigation.** Not found by this milestone's own work — found by
an *independent* review pass (`docs/MILESTONE_REPORT_M4.md`) reading
`DashboardLayout` and `SidebarInset` fresh, rather than re-trusting the
Milestone 4 session's own "0 violations" `axe-core` result. Confirmed
via `page.locator("main").count()` returning 2 on shell pages
pre-fix, 1 post-fix, across every route.

**Decision.** Change `DashboardLayout`'s inner wrapper from `<main>` to
`<div>`.

**Outcome.** Verified 1 `<main>` per route across all 7 (6 shell pages
+ 404), confirmed via both a landmark count check and a widened
`axe-core` pass.

**Lessons learned.** The same lesson as this project's earlier entries,
from a different angle: this time it wasn't a runtime crash a
browser-click would catch, and it wasn't a WCAG-tagged `axe-core`
violation either — it took a human reading the actual rendered HTML
structure against the HTML spec. Automated tooling and manual review
catch different classes of defect; neither replaces the other.

### `axe-core`'s tag scope, and following through on a risk finding

**Problem.** See Interview Highlights #4.

**Investigation.** The M4 report's Risk #1 stated the hypothesis
(rule-tag scope could hide structural checks) but didn't test it — a
review has to stop somewhere. This milestone tested it: re-ran the
exact same audit setup with `best-practice` added to the tag list.

**Decision.** Adopt widened tags (`wcag2a`, `wcag2aa`, `wcag21a`,
`wcag21aa`, `best-practice`) as the standard going forward, not a
one-off for this milestone.

**Outcome.** Two real violations surfaced immediately —
`heading-order` and `page-has-heading-one` — both fixed (Interview
Highlights #5), then re-verified at 0 violations across all 7 routes
with the *same widened* scope, not a reversion to the narrower one.

**Lessons learned.** A documented risk that's never tested is just a
sentence. The value came from actually running the wider audit, not
from having predicted it might find something.

---

## 2026-07-10 — Base UI composition uses `render`, not `asChild`

**Problem.** Composing `Tooltip` + `Button` (`<TooltipTrigger asChild><Button>...</Button></TooltipTrigger>`)
produced an invalid nested `<button>` inside `<button>` and a React
hydration error — one that `tsc`, ESLint, and `next build` all passed
without complaint.

**Investigation.** `asChild` is Radix UI's polymorphism convention,
and this project's shadcn setup uses Base UI, not Radix. Checked
`TooltipTrigger`'s actual prop type: `TooltipPrimitive.Trigger.Props`
had no `asChild` field at all. Traced the real mechanism to
`BaseUIComponentProps`'s `render` prop — "Accepts a `ReactElement` or a
function that returns the element to render," documented directly in
Base UI's own type comments.

**Decision.** Switched to `<TooltipTrigger render={<Button ... />} />`
— Base UI merges the trigger's own props/ref into the render element
rather than wrapping it, producing one final `<button>`.

**Outcome.** Hydration error gone, confirmed via a real rendered page
(not just the type system). Documented as the standard composition
pattern in `docs/PROJECT.md`; every subsequent `render`-prop usage
across Milestones 3–4 followed this correctly on the first attempt.

**Lessons learned.** Training-data assumptions about "how shadcn
components compose" don't transfer across the Radix→Base UI switch.
When a composition pattern looks idiomatic but behaves wrong, check
the actual prop type before assuming the runtime is buggy.

---

## 2026-07-10 — Measuring color contrast correctly (not `getComputedStyle`)

**Problem.** Needed to verify `success`/`warning`/`destructive` token
contrast against WCAG AA (4.5:1) empirically, not by eye. First attempt:
render a probe element with the color, read `getComputedStyle(el).color`,
parse as `rgb(...)`.

**Investigation.** The parse failed — `getComputedStyle` returned
`oklab(0.6 -0.111432 0.0669549 / 0.1)`, not `rgb(...)`. CSS Color 4's
computed-value serialization preserves the *function* used
(`oklch()`/`color-mix()` results serialize as `oklab()`), it doesn't
force-resolve to sRGB just because a value was requested. Any
contrast script relying on regex-parsing `getComputedStyle`'s `color`
would silently produce wrong (or in this case, crashing) results for
any modern CSS color function.

**Decision.** Switched to a Canvas2D probe: set `ctx.fillStyle` to the
color expression, `fillRect`, then `getImageData` — canvas always
paints in concrete sRGB bytes regardless of input color space, because
that's what actually reaches the screen.

**Outcome.** Produced exact, verifiable contrast ratios (cross-checked
against axe-core's own numbers — matched to 2 decimal places),
including alpha-composited "badge tint" backgrounds via manual
over-compositing, which `getComputedStyle` can't do at all (it returns
the un-composited color, not what's visually rendered).

**Lessons learned.** For anything measuring *rendered* color (contrast,
pixel sampling, visual diffing), go through a rendering API (Canvas,
screenshot), not the CSSOM — the CSSOM describes the value as
authored/computed, not as painted.

---

## 2026-07-10 — `shadcn add` will silently overwrite customized files

**Problem.** Milestone 4 needed the `sidebar` primitive. `npx shadcn
add sidebar --dry-run` reported it would overwrite `button.tsx`,
`input.tsx`, `skeleton.tsx`, and `tooltip.tsx` — all already modified
with Milestone 3/3.1 accessibility work, most critically `Button`'s
compile-time `aria-label` enforcement.

**Investigation.** The CLI bundles every dependency a component needs
as part of its registry entry and re-stamps its own canonical copy on
`add`, with no diffing against local changes beyond a raw file-content
overwrite. Used `--view src/components/ui/sidebar.tsx` to read the
actual source before deciding: it does plain `import { Button } from
"@/components/ui/button"` — a normal import, not anything requiring
the CLI's exact vendor bytes.

**Decision.** Extracted the four genuinely new files (`sidebar.tsx`,
`sheet.tsx`, `separator.tsx`, `use-mobile.ts`) by hand via `--view`,
never running `add` against files that already existed. Along the way,
found and fixed two accessible-name gaps *in the vendor source itself*
— `SidebarTrigger` and `SheetContent`'s close button both relied on an
`sr-only` text child rather than `aria-label`, which fails to
typecheck against this project's stricter `Button`.

**Outcome.** `sidebar`/`sheet` integrated with zero loss of Milestone
3.1's work, verified via `grep -c "aria-label"` on `button.tsx`
before/after. The type-level enforcement caught real gaps in
shadcn's own generated code, not just hypothetical misuse.

**Lessons learned.** Never run `shadcn add` against an already-
customized file without `--dry-run` first — now a standing project
rule (`docs/adr/0001-design-system.md`). Second: a strict, compile-time
accessibility contract earns its complexity the moment it catches
something in code you didn't write yourself.

---

## 2026-07-10 — Base UI's `Menu.GroupLabel` requires `Menu.Group`

**Problem.** Opening the header's user-menu `DropdownMenu` crashed at
runtime: `Base UI: MenuGroupContext is missing. Menu group parts must
be used within <Menu.Group> or <Menu.RadioGroup>.` Static checks
(`tsc`, ESLint, `next build`) had all passed.

**Investigation.** `DropdownMenuLabel` (`MenuPrimitive.GroupLabel`) was
used directly inside `DropdownMenuContent`, without a
`DropdownMenuGroup` wrapper. In Radix (and casual assumption), a
standalone label often works without an explicit group; Base UI
enforces the ARIA grouping relationship at runtime via context and
throws rather than degrading silently.

**Decision.** Wrapped the label + items in `<DropdownMenuGroup>`.

**Outcome.** Confirmed fixed by re-running the same interaction test
that caught it (Playwright click + console-error capture) — the crash
is gone and the semantic grouping is now also more correct (the label
is properly associated with its items for assistive tech, not just
visually adjacent).

**Lessons learned.** Third instance this session of the same pattern:
a runtime-only failure invisible to every static check, caught only by
actually opening the menu in a browser. The pattern is now explicit
project practice, not incidental — every new interactive primitive
gets clicked, not just typechecked.

---

## 2026-07-10 — `nativeButton` and composing `Button` as a link

**Problem.** The 404 page's `<Button render={<Link href="/" />}>Back
to Overview</Button>` logged a Base UI warning in the browser console
(page still worked, but the warning is a real signal): rendering as a
non-`<button>` while the component still expects native button
semantics can affect forms/accessibility.

**Investigation.** Base UI's `Button` defaults to assuming it renders
a native `<button>` (`nativeButton: true`) for correct implicit ARIA
role and form-submission behavior. Overriding via `render` to output
an `<a>` changes that without telling the component, which is exactly
what the warning flags.

**Decision.** Added `nativeButton={false}` to explicitly declare "this
is intentionally not a native button" — checked the rest of the
codebase (`grep`) for the same `Button render=` pattern to see if
other instances needed the same fix; none did (the only other
button-as-link usage, in `AppSidebar`, uses `SidebarMenuButton`'s
different, unaffected `useRender` pattern).

**Outcome.** Warning gone, re-verified via the same Playwright
console-error capture used throughout this milestone.

**Lessons learned.** "Composing a Button to act as a navigation link"
is common enough (call-to-action buttons that navigate) that this is
now a documented pattern, not a one-off fix: pair `render={<Link .../>}`
with `nativeButton={false}` whenever `Button` is deliberately used as
a link.