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
- ~~**`SitePolicy`/`PostPolicy` authorization checks are not eager-load
  safe**~~ **Resolved, Milestone 8.** Rather than eager-load
  `workspace.users` before a per-row loop (the fix this item
  originally named), the Current Workspace Resolver architecture
  removes the loop entirely — `index()` actions authorize the
  *workspace* once (via `ResolveCurrentWorkspace` middleware) and then
  filter with a plain `WHERE workspace_id = ?`, never a per-row Gate
  check. See
  [[0006-authentication-architecture]](adr/0006-authentication-architecture.md).

### Medium Priority

- **Open `DropdownMenu`/`Popover` content isn't contained by a landmark**
  (found Milestone 8, pre-existing since Milestone 4 — `axe-core`'s
  `region` rule, moderate severity). Base UI portals popup content
  (the header's user menu, notifications popover) to `document.body`
  for correct floating-UI stacking, outside the `<header>`/`<main>`
  landmark structure — flagged only while a menu is actually open (0
  violations with every menu closed, confirmed via this milestone's own
  audit). Not fixed here: the underlying mechanism is shared by every
  Base UI popup primitive project-wide (`DropdownMenu`, `Popover`,
  `Tooltip`), so a real fix is a design-system-level decision (e.g. an
  `aria-owns` relationship, or accepting `region`'s known false-positive
  rate against portaled interactive overlays), not a one-component
  patch — worth a dedicated look whenever `ui/dropdown-menu.tsx`/
  `ui/popover.tsx` are next touched.
- ~~**Sidebar `isActive` uses exact match, not prefix match**~~
  **Resolved, Milestone 9.** `/wordpress/[id]` is the first nested
  route this app has, which is exactly the trigger this item was
  waiting for. Matches a full path segment (`pathname === item.href ||
  pathname.startsWith(item.href + "/")`), not a bare `startsWith` —
  the specific risk this item flagged (`/dashboard` wrongly
  prefix-matching a future `/dashboard-settings`) is avoided by the
  trailing slash.
- ~~**Recent Drafts' deterministic failure is module state, not
  request state**~~ **Resolved, Milestone 10.1.** Recent Drafts now
  reads real data (`GET /posts?status=unpublished`) — the contrived
  mock failure-injection this item described no longer exists to have
  the bug. Real network/API failures now drive the Error state
  instead, the same as every other real-data widget.
- ~~**Six of nine dashboard widgets remain on the mock service
  layer**~~ **Resolved, Milestone 10.1.** The remaining six (Recent
  Activity, Analytics Preview, Recent Drafts, System Health, Quick
  Actions, AI Assistant Preview) were each reviewed individually.
  Four became real (Recent Activity derived from `Post`/`Site` data;
  Analytics Preview from `AnalyticsSnapshot`; Recent Drafts from
  `Post::scopeUnpublished()`; System Health from real DB/Site/storage
  checks). Quick Actions is half-real (two of four actions now
  navigate to real destinations; the other two stay honestly disabled
  since no real target exists for them yet). AI Assistant Preview
  stays deliberately mocked — no real AI provider integration exists,
  unchanged from its Milestone 5/7 deferral (Milestone 14's job). See
  `docs/MILESTONE_REPORT_M10_1.md`.
- ~~**Sites/Posts index endpoints have no pagination**~~ (found
  Milestone 7; reaffirmed Milestone 10 once `/wordpress/[id]/posts`
  started rendering real `Post` rows; reviewed again and deliberately
  deferred in Milestone 10.1, still needing the real page-size decision
  this item had always named). **Posts resolved, Milestone 17** —
  measured first (142ms for 6,012 unpaginated rows against a
  temporarily inflated dataset), then fixed: `page`/`per_page` (default
  50, max 100) via a `meta.pagination` block on the existing envelope.
  `PostsTable` gained Previous/Next controls; `RecentDrafts` (identical
  unbounded shape) capped at `per_page=5` instead. See
  [[0015-performance-and-scalability]](adr/0015-performance-and-scalability.md).
  **Sites remains open, by design** — no measured problem at a
  workspace's realistic scale (single digits to tens of connected
  sites); revisit only if that changes.
- **Workspace deletion has no dedicated flow** (found Milestone 7, by
  design). `Workspace::delete()` today hard-deletes and cascades to
  every site/post — correct as a database constraint, but a real
  product needs a deliberate tenant-deletion flow (confirmation, data
  export, grace period) before this is ever exposed through an API
  endpoint. No such endpoint exists yet, so not urgent — but flagged
  before one gets added casually.
- ~~**`UrlSafetyValidator`'s SSRF guard doesn't resolve hostnames**~~
  **Resolved, Milestone 19** — a new injectable `DnsResolver` resolves
  a hostname and checks every address it currently points at against
  the same private/reserved-range filter the literal-IP path already
  used (found Milestone 9, by design — see
  [[0007-wordpress-integration-architecture]](adr/0007-wordpress-integration-architecture.md)'s
  Security section). Scoped to exactly the named gap, not full
  DNS-rebinding (time-of-check/time-of-use) protection — pinning the
  resolved IP through to the actual HTTP client connection is real
  further hardening, named as future work, not currently justified by
  this app's threat model. Building the fake resolver needed for
  testing it surfaced a real, unrelated Pest configuration bug — see
  this file's 2026-07-22 dated entry. See
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md).
- **`wordpress_version`/`php_version` are always `null`** (found
  Milestone 9, by design). Stock WordPress doesn't expose either via
  its public REST API without a companion plugin — see the ADR's
  "Version Detection" section for the full accounting of what is and
  isn't obtainable today. Revisit if a WP Studio companion plugin ever
  gets built (see the ADR's Future Extensibility).
- ~~**`WordPressPostMapper::upsert()` runs one lookup query per
  WordPress item, not a batch operation**~~ **Resolved, Milestone 17.**
  (Found Milestone 10, by design — see
  [[0008-content-synchronization]](adr/0008-content-synchronization.md)'s
  Performance section; up to ~100 `SELECT` queries per page at
  `per_page=100`, explicitly accepted pending "a measured problem.")
  Measured at 300 queries/1,297ms for 100 items against a temporarily
  inflated dataset — a real problem. Fixed via a new
  `preloadExisting()` method on the `ContentTypeMapper` contract,
  batch-loading each page's existing posts in one query before the
  upsert loop; re-measured after the fix at 201 queries/1,094ms, the
  full predicted 100-query reduction. See
  [[0015-performance-and-scalability]](adr/0015-performance-and-scalability.md).
- ~~**Content sync is fully synchronous**~~ **Resolved, Milestone 11.**
  `POST /sites/{site}/sync` now dispatches `SyncWordPressPostsJob`
  instead of blocking the request — see
  `docs/adr/0009-background-job-platform.md`. The 20-page (2,000-post)
  cap itself is **not** removed, only the reason it originally existed
  changed: it's now a bounded-execution safety measure inside an async
  job with its own 300s timeout, the same "don't process unbounded
  external data in one pass" discipline, just no longer motivated by
  blocking an HTTP request.
- **Content sync fetches only post metadata, no post body/content**
  (found Milestone 10, by design). Title, mapped status, dates, and
  the public URL are stored; the raw HTML body isn't fetched or
  persisted. Storing it ahead of an actual editing/Publishing feature
  needing it would be speculative — see the ADR's Rejected
  Alternatives.
- ~~**System Health's `backgroundQueue` is an honest, hardcoded
  placeholder**~~ **Resolved, Milestone 11.** `QueueHealthChecker`
  reports real `pending`/`failed` counts from the `jobs`/`failed_jobs`
  tables, the real configured driver, and a `degraded` status derived
  from actual failed-job presence — verified live against a real
  failed job in this milestone's own browser verification, not just
  asserted.
- **`DashboardService::recentActivity()` runs three separate queries
  and merges/sorts them in PHP** (found Milestone 10.1, by design).
  No persisted "activity log" table exists — the feed is derived
  live from `Post`/`Site` columns each request. Fine at today's real
  usage (a handful of sites/posts per workspace, one request per
  dashboard load); revisit with a real activity-log table only if a
  genuine usage pattern justifies the added write-path complexity —
  the same "don't build for scale that doesn't exist yet" reasoning
  `docs/adr/0005-domain-model.md` already applied elsewhere.
- **Settings is real but read-only — no editable preferences exist**
  (found Milestone 10.1, by design). `GET /api/v1/settings` returns
  genuine workspace/user data instead of a "not implemented" message,
  but there's no form, no `PATCH` endpoint, and no decided answer to
  "what should a user actually be able to change here" — the same
  category of deferred product decision as Registration (Milestone 8)
  and the "AI Jobs" table (Milestone 7). Build the real editable
  feature once that decision is made, not before.
- ~~**No process supervision for `queue:work`**~~ **Resolved,
  Milestone 19** — not with a hand-rolled Supervisor config inside the
  container, but by running `queue`/`scheduler` as their own Railway
  services (Railway's own per-service process model restarts a crashed
  process, the identical real guarantee). See
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md)
  and `docs/DEPLOYMENT.md` §5.
- **`RefreshSiteMetadataJob` is not wired to the existing manual
  "Refresh Metadata" button** (found Milestone 11, by design). The
  button stays synchronous — a single, fast, bounded WordPress
  request, unlike content sync's paginated fetch — so immediate
  feedback is still the right UX. The job exists and is real,
  currently consumed only by the new daily Scheduler task; wiring the
  manual button to it too is a trivial, low-risk future change if a
  reason to make that action async ever appears.
- **`job_batches` is provisioned (Laravel default) but unused** (found
  Milestone 11, by design). Nothing in this milestone dispatches
  multiple related jobs that need batch-level coordination
  (all-succeeded / any-failed tracking). A real candidate once a
  "sync every site in a workspace" bulk action exists; not built
  speculatively ahead of that.
- **No lightweight notification fires on job completion beyond the
  polling-driven status badge** (found Milestone 11, reviewed and
  deliberately not built — see the brief's own "do not build a
  complete notification platform" instruction). The site's status
  badge and `SyncSummary` card already update automatically via
  polling the moment a job completes; a separate toast/notification-
  store entry would duplicate that signal for marginal benefit. Revisit
  if a future milestone needs completion visibility beyond the page the
  user triggered it from (e.g., "notify me even if I've navigated
  away").
- **No thumbnail or responsive-image generation** (found Milestone 12,
  by design). Every rendered image — grid, list, preview, post detail
  — serves the original upload/download at full resolution. Named as
  Milestone 17 (Performance & Scalability)'s natural starting point; see
  `docs/adr/0010-media-platform.md`.
- **No virus scanning on uploaded/downloaded media** (found Milestone
  12, reviewed per the brief's own instruction, explicitly deferred).
  **Evaluated again, Milestone 19 — still not implemented as code**,
  deliberately: no real scanning service exists to build or test
  against without live infrastructure that milestone's
  "deployment-ready, not deployed" scope excluded. Documented as a
  concrete recommendation (a ClamAV sidecar, or the object-storage
  provider's own scanning add-on) in `docs/DEPLOYMENT.md` rather than
  speculative, unverifiable code. See
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md).
- **Media Platform MIME allow-list is images only** (found Milestone
  12, by design). `config('media.allowed_mimes')` covers `jpg`,
  `jpeg`, `png`, `gif`, `webp` — `svg` deliberately excluded (a real
  stored-XSS vector via inline `<script>`). Document/report types the
  brief names as future producers are a one-line config extension, not
  built ahead of a real consumer.
- **No row-level media deduplication or `media_mediable` pivot** (found
  Milestone 12, by design). Storage-level dedup (reusing bytes already
  on disk via a content hash) is real; sharing one `Media` row across
  two independent attachments with separate lifecycles is not — no
  current feature needs one file backing two attachments
  simultaneously. See `docs/adr/0010-media-platform.md`'s Alternatives
  Considered.
- **`posts`' `(site_id, wordpress_post_id)` unique index carries the
  same SoftDeletes/uniqueness tradeoff Milestone 12 found and fixed on
  `media`** (found Milestone 12, newly documented — the underlying
  code is unchanged since Milestone 10). Not a functional bug today —
  no current workflow re-creates a soft-deleted post with the same
  WordPress ID — but a real risk if that scenario ever becomes
  reachable. See the dated entry below for the full investigation.
- **No GraphQL mutations** (found Milestone 13, by design). Every
  write in this application still goes through REST. A real candidate
  only if a genuine product need for a GraphQL write path emerges; not
  built speculatively ahead of one.
- **GraphQL has no dedicated rate limiter** (found Milestone 13, by
  design). Unlike `wordpress-connection`/`media-upload`, `/api/v1/graphql`
  inherits no throttle — acceptable today since it's read-only and
  every resolver re-uses existing, already-bounded service calls with
  no new expensive aggregation. Worth a look if the schema ever grows
  to include anything expensive.
- **`posts`/`sites`/`media`'s REST endpoints are not exposed via
  GraphQL** (found Milestone 13, by design — see
  `docs/adr/0011-graphql-layer.md`'s Alternatives Considered). A real
  candidate only if one of them develops a genuine variable-shape
  aggregation need the way the Dashboard did; not built ahead of that
  need, since all three already have complete, tested REST CRUD.

### Low Priority

- **`src/styles/` exists but is empty and undocumented** (found
  Milestone 4). Either populate it with a real purpose or remove it —
  an empty, unexplained directory is a small but real ambiguity for
  the next person navigating the codebase.
- **`components.json`'s `iconLibrary`/style presets are hand-picked
  and undocumented as to why** (found Milestone 4). Low risk, but a
  future contributor changing them wouldn't know what would break.
- ~~**Root `README.md` is still Create Next App's default boilerplate**~~
  **Resolved** in the post-Milestone-7 documentation session — now
  points to `docs/AI_ENGINEERING_CONTEXT.md`, `docs/PROJECT.md`, and
  `backend/README.md` instead of Next.js's generic getting-started text.
- **Local development runs on SQLite, not a server database** (found
  Milestone 6, by design — see the ADR's Trade-offs). Fine for this
  milestone's architecture-only scope; a real deployment target should
  make (and document) a deliberate MySQL/PostgreSQL choice, not
  inherit SQLite by default. **Update, Milestone 7:** confirmed this
  choice has a real, non-hypothetical cost — SQLite's lack of
  foreign-key auto-indexing directly caused the two missing-index
  findings this milestone's self-review caught (see
  [[0005-domain-model]](adr/0005-domain-model.md)). **Resolved,
  Milestone 19:** PostgreSQL chosen, and — unlike the index-auditing
  worry above predicted — verified against a real Postgres 16
  container with zero code changes and zero test failures across the
  full 145-test suite; the index gaps SQLite's own behavior surfaced
  back in Milestone 7 had already been fixed by the time this
  verification ran. Local development stays SQLite, deliberately — see
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md).

### Deferred Priority

- ~~**AI Assistant Preview has no real backend**~~ **Resolved,
  Milestone 14.** `Generate` now calls a real Claude/Gemini-backed
  pipeline. See
  [[0012-ai-content-generation]](adr/0012-ai-content-generation.md).
- ~~**No "AI Jobs" table or model exists**~~ **Resolved, Milestone
  14.** `ai_jobs` exists, designed against two real provider
  integrations rather than guessed at. See
  [[0012-ai-content-generation]](adr/0012-ai-content-generation.md).
- **No AI generation-history UI, no site/post-targeted generation, no
  streaming responses** (found Milestone 14, by design). `ai_jobs`
  rows persist but nothing lists past generations; the widget has no
  site selector; both provider SDKs support streaming but
  `AiClientContract::generate()` is request/response only. Each is a
  real future feature named in
  [[0012-ai-content-generation]](adr/0012-ai-content-generation.md)'s
  Future Evolution, not built ahead of a UI that asks for it.
- **Live, successful-generation browser verification wasn't completed
  for Milestone 14** (found Milestone 14). The account's Gemini
  free-tier daily quota was exhausted during verification, after
  request format, model accessibility, auth, queue processing, retry/
  backoff, and error handling were all confirmed live against the real
  API. The completed-state UI is covered by an automated integration
  test (`AiGenerationTest`) instead of a live demo. Revisit with a
  paid-tier Gemini key or a real Anthropic key. See
  [[0012-ai-content-generation]](adr/0012-ai-content-generation.md)'s
  "Live Verification" section.
- ~~**Every backend API route is unauthenticated**~~ **Resolved,
  Milestone 8.** Sanctum cookie/session auth, every route behind
  `auth:sanctum` except `/health` and `/login`, `SitePolicy`/
  `PostPolicy` wired into `SiteController`/`PostController` unchanged.
  See [[0006-authentication-architecture]](adr/0006-authentication-architecture.md).
- **No self-registration or onboarding flow** (found Milestone 8, by
  design — see
  [[0006-authentication-architecture]](adr/0006-authentication-architecture.md)'s
  "Why registration is deferred"). Deliberately not built without a
  real answer to "which workspace does a new user land in" — the same
  reasoning [[0005-domain-model]](adr/0005-domain-model.md) already
  applied to deferring `WorkspaceService`. Login is against
  `DemoDataSeeder`'s seeded user until a future onboarding milestone.
- ~~**Sanctum's cookie-session auth needs a shared registrable domain
  in production**~~ **Resolved, Milestone 19** — with zero code
  changes. `config/cors.php`/`config/sanctum.php`/`config/session.php`
  were already fully env-driven; the actual fix is a deployment
  decision (custom subdomains of one registrable domain, e.g.
  `app.yourdomain.com`/`api.yourdomain.com` — subdomains of the same
  registrable domain count as same-site for `SameSite` cookie
  purposes), documented in `docs/DEPLOYMENT.md` §4. See
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md).
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
- ~~**Analytics, AI, and Settings API domains are still placeholder
  endpoints**~~ **Partially resolved, Milestone 14** — AI is real now
  (see above). Analytics is real (Milestone 10.1); Settings is real
  but read-only (Milestone 10.1) — see `docs/ROADMAP.md`.
- ~~**No production Docker image or deployment target**~~ **Backend
  image resolved, Milestone 19** (found Milestone 15, by design — see
  [[0013-docker-development-environment]](adr/0013-docker-development-environment.md)).
  `docker/production/php.Dockerfile` — multi-stage, built and
  smoke-tested locally. This milestone's own dev Dockerfiles stay
  unchanged, deliberately. Actual deployment (a live URL) remains not
  done — see
  [[0017-cloud-deployment-and-security-hardening]](adr/0017-cloud-deployment-and-security-hardening.md).
- **No general host-UID/GID-matching mechanism in the Docker setup**
  (found Milestone 15, by design). Live validation found and fixed two
  specific Windows-bind-mount permission problems with targeted
  `chmod`/`chown` calls, not a general Sail-style `WWWUSER` build-arg
  mechanism — deferred until a Linux-host contributor hits a different
  path with the same class of problem. See
  [[0013-docker-development-environment]](adr/0013-docker-development-environment.md)'s
  Deferred section.
- **Redis is present in `docker-compose.yml` but unused** (found
  Milestone 15, by design). `CACHE_STORE`/`SESSION_DRIVER`/
  `QUEUE_CONNECTION` all stay on `database`; the container only starts
  under `docker compose --profile optional up`. **Evaluated, Milestone
  17: not integrated, on measured evidence.** `DashboardService`'s
  aggregates — the most obvious caching candidate — measured 5–12ms
  even at an inflated 34-site/6,012-post dataset, already fast enough
  that a cache layer's invalidation complexity wouldn't be worth the
  saving. A real decision, not a further deferral; see
  [[0015-performance-and-scalability]](adr/0015-performance-and-scalability.md).
  Revisit only if future real usage data shows a specific, repeated,
  expensive read.
- **Frontend test coverage is bounded to critical flows, not every
  component** (found Milestone 16, by design). See
  [[0014-frontend-testing-and-ci]](adr/0014-frontend-testing-and-ci.md).
- **No end-to-end (Playwright) tests run in CI** (found Milestone 16,
  by design). Every milestone's own manual live verification already
  uses Playwright ad hoc; formalizing that into a permanent CI suite is
  separate future scope, not built here.
- **No test coverage reporting/threshold configured** (found Milestone
  16, by design) — not a meaningful signal at this project's current
  test count.

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

### Milestone 8 (Authentication & Authorization)

**1. Revising a design mid-review, not just approving or rejecting it.**
*Problem:* the initial architecture proposal — every workspace-scoped
endpoint takes an explicit, policy-checked `workspace_id` — is
correct and would have worked. *Chosen solution:* replaced it, on
explicit direction, with a centralized Current Workspace Resolver
(`CurrentWorkspaceResolver` → middleware → a `scoped()` context
binding) before any implementation started. *Trade-offs:* more
architecture (three new classes) for a milestone that could have
shipped with one. *Why this approach:* the simpler design pushes a
"remember to scope this to the current workspace" responsibility onto
every future frontend call site and every future controller, forever;
centralizing it once means a future workspace switcher or subdomain-
based tenancy is a change to one class, not an audit of every place
`workspace_id` might have been read. Worth recording because the
*review* caught this, not the implementation — the cheaper design
would have shipped fine and only shown its cost two or three
milestones later.

**2. Finding two real vulnerabilities by reading code, not by guessing
where auth belongs.** *Problem:* "add `auth:sanctum`" was the
milestone's obvious first move, but reading `DashboardService` and
`IndexSitesRequest` before writing anything surfaced that
`DashboardService::summary()` aggregated every workspace in the
database with no scoping at all, and `IndexSitesRequest` accepted any
`workspace_id` with no membership check. *Chosen solution:* fixed both
as part of this milestone, not filed as follow-up tickets. *Why this
matters for an interview answer:* neither was hypothetical — both were
verified by tracing the actual query code, and both were invisible in
every previous milestone's testing because only one workspace has ever
existed in seeded data. "It passed every test" and "it's correct" are
different claims when the tests never had a second tenant to fail
against.

**3. Choosing TanStack Query over Zustand for auth state by applying
an existing precedent, not re-deciding it.** *Problem:* the milestone
brief asked for an "auth context/store," which reads naturally as
"add a Zustand store." *Chosen solution:* `useCurrentUser()`
(`useQuery(["auth","user"])`) instead — no Zustand store at all.
*Why this approach:* `docs/adr/0003-dashboard-data-architecture.md`
already drew this exact line for every other piece of server data;
the authenticated user is server state (it lives in the `users` table,
can change for reasons the client didn't initiate, like an expired
session) — a Zustand copy would just be a second cache that can drift
from what the server actually thinks. Recognizing "this is the same
category of decision already made" avoided re-litigating it from
scratch, the same lesson the Milestone 5 "static route with a
clock-dependent greeting" entry drew from a different angle.

**4. A Sanctum SPA testing gotcha that looked like a production bug at
first.** *Problem:* HTTP-level login/logout Pest tests failed with
`RuntimeException: Session store not set on request`, and later,
`assertGuest()` reporting a user as still authenticated after a
real logout call succeeded. *Investigation:* Sanctum's
`EnsureFrontendRequestsAreStateful` only attaches session middleware to
requests it recognizes as "from the frontend" (via `Referer`/`Origin`),
which a plain `postJson()` call doesn't set — fixed by sending a
`Referer` header matching a `SANCTUM_STATEFUL_DOMAINS` entry. The
second failure was different: Laravel's test client doesn't carry
cookies between separate simulated requests the way a real browser
does, and the `array` session driver this test suite runs under (see
`phpunit.xml`) doesn't persist store state across requests either — so
neither "log in, then check the guard" nor "log in, log out, reuse the
old cookie" could reliably prove invalidation the way they would
against a real server. *Chosen solution:* assert on the one thing that
*is* observable within a single response — `session()->invalidate()`
regenerates the session ID, so the logout response's own Set-Cookie
differs from the one sent in. *Why this matters:* both fixes are
testing-environment-specific, not application bugs (the actual
`AuthController::logout()` code — `Auth::guard('web')->logout()` +
`session()->invalidate()` — is the standard, correct Laravel
implementation throughout) — worth distinguishing "my test's
assumptions about the environment are wrong" from "my code is wrong"
before changing either one, the same discipline the Milestone 7
off-by-one entry required.

**5. A dev-mode-only navigation bug that wasn't a bug.** *Problem:*
browser-driven verification of the unauthenticated-redirect flow
showed `router.replace("/login")` being called (confirmed via console
log) but the URL never actually changing, even after a 10-second wait.
*Investigation:* Next.js dev mode's Fast Refresh was rebuilding
immediately after the navigation call on every run, including runs
where no file had just been edited — consistent with an HMR-related
remount interrupting the in-flight client-side navigation, not
anything `ProtectedLayout` itself was doing wrong. Confirmed by
re-running the identical verification against a production build
(`next build && next start`): the redirect fired correctly on the
first attempt, with a `framenavigated` event to `/login` observed
directly. *Chosen solution:* verify auth flows against a production
build, not the dev server, when the behavior under test involves
client-side navigation. *Why this matters:* this is the same lesson
Milestone 5's zombie-dev-server entries drew from a different angle —
distinguishing a real defect from an artifact of the local tool chain
requires actually isolating which one changed, not assuming the
newest code is guilty by default.

### Milestone 9 (WordPress Integration Platform)

**1. `retry()`'s default `throw: true` silently ate my own exception
mapping.** *Problem:* `WordPressConnectionTest`'s "rejects invalid
credentials" case failed with an uncaught
`Illuminate\Http\Client\RequestException: HTTP request returned status
code 401`, even though `HttpWordPressClient::fetchRequired()` has its
own explicit `if ($response->status() === 401 ...)` branch that should
have caught it first. *Investigation:* Laravel's `PendingRequest::retry()`
takes a fourth parameter, `$throw`, defaulting to `true` — meaning
`retry()` throws its own `RequestException` on a failing response once
retries are exhausted, *before* control ever returns to the calling
code to inspect the response itself. My `retry(2, 200, when: fn ($e)
=> $e instanceof ConnectionException)` call only customized *when to
retry*, not this separate, independently-defaulted throwing behavior.
*Chosen solution:* added `throw: false` explicitly. *Why this matters
for an interview answer:* a method having more default behavior than
its name and the arguments you passed it suggest is a real, general
risk with fluent builder APIs — worth reading a method's full
signature (not just the parameters you intend to use) before assuming
you've fully specified its behavior, especially for anything with
security/correctness implications like which exceptions get thrown.

**2. Treating SSRF as the headline risk, not an afterthought.**
*Problem:* the milestone brief's own failure-mode list (network
failures, invalid credentials, unreachable hosts, ...) never mentioned
SSRF by name. *Chosen solution:* named it explicitly in the
architecture review, before writing any code, as the actual
first-order risk "connect to a URL a workspace member supplies"
introduces — then built `UrlSafetyValidator` and a dedicated test
(`Http::assertNothingSent()`, proving the request never even attempts
to go out) proving it's closed, not just documented as a concern.
*Why this matters for an interview answer:* a brief's own named list of
concerns is a floor, not a ceiling — the most consequential risk in a
"fetch a user-supplied URL" feature is the one general-purpose failure-
mode brainstorming (network failures, timeouts) doesn't surface on its
own, because it's a security property of the feature's *shape*, not a
reliability property of the specific calls it makes.

**3. Choosing graceful degradation over an all-or-nothing handshake,
deliberately.** *Problem:* three of five WordPress REST endpoints this
integration calls (themes, plugins, users) are each independently
capability-gated — a real Application Password created by an editor,
not an administrator, will legitimately 403 on some of them.
*Chosen solution:* `fetchOptional()` treats that as "this field isn't
available" (`null`), not a failed connection; only the two calls that
*prove* the URL is WordPress and the credential works at all
(`fetchRequired()`) can fail the whole attempt. *Why this approach:*
the alternative — requiring every capability to succeed — would reject
a real, legitimately-connectable site over a permissions boundary that
has nothing to do with whether the connection itself is valid,
punishing the common case (a non-admin Application Password) to
simplify a small amount of code.

### Milestone 10 (Content Synchronization Platform)

**1. Resolving a pre-existing domain collision before writing any sync
code.** *Problem:* `Post` had existed since Milestone 7 with full CRUD
but zero frontend consumers — this milestone's brief asked to sync
WordPress posts into "the posts table" without addressing whether a
synced post and a manually-created one are the same kind of thing.
*Chosen solution:* extended the existing `posts` table with nullable
sync-tracking columns rather than building a parallel table, reasoning
explicitly through every existing and future consumer (`PostController`,
`PostPolicy`, `PostResource`, a not-yet-built Publishing milestone) and
concluding they all treat "a post" as one concept regardless of
origin. *Why this matters for an interview answer:* a milestone brief
naming a table by its plural noun ("the posts table") is not the same
as the brief having already decided the data model — surfacing and
deciding a genuine schema question the brief left implicit, before
writing a migration, is exactly what an architecture-review stage is
for.

**2. Building a generic engine instead of a generic schema.**
*Problem:* the brief required the sync layer to generalize to future
Pages/Media/Categories/Tags without hardcoding "Posts" — the naive
reading of that requirement is "design one schema that fits every
content type." *Chosen solution:* recognized this as the identical
trap a prior milestone's ADR had already named and rejected for an
unrelated table ("AI Jobs" — guessing a schema before a second real
case exists to validate it against). Put the genericity in the
*orchestrator* (`ContentSyncService` knows only about a small
`ContentTypeMapper` contract, never about "posts") and left the schema
concrete, one mapper at a time. *Why this matters for an interview
answer:* "make it generic" is frequently satisfied by fixing the
*process* and deferring the *data shape*, not by guessing the data
shape further upfront — recognizing which axis a requirement is
actually about is the harder, more valuable part of the decision.

**3. Choosing a content hash over a timestamp for idempotency, and
proving the choice with a test that would fail under the timestamp-only
alternative.** *Problem:* "avoid duplicate imports, support repeat
synchronization" could be satisfied by comparing WordPress's own
`modified_gmt` against a stored value. *Chosen solution:* store both,
but gate the actual skip/update decision on a sha256 hash of the
mapped, change-relevant fields — resilient to a WordPress site's clock
being wrong or a `modified_gmt` that wasn't bumped for some reason.
*Why this matters for an interview answer:* trusting a single
external system's self-reported timestamp as your only correctness
signal is a common, easy-to-miss fragility — a hash comparison
degrades gracefully even when the upstream signal you'd naively rely
on turns out to be unreliable.

### Milestone 10.1 (API Completion & Frontend Migration)

**1. Auditing six mock widgets individually instead of applying one
migration pattern uniformly.** *Problem:* the brief's instruction
("eliminate mock dependencies") could be read as "migrate everything
the same way." *Chosen solution:* reviewed each of the six remaining
mocked widgets against what real data actually existed to back it —
Analytics Preview and System Health had real underlying tables
(`AnalyticsSnapshot`, `Site.storage_used_mb`) ready to aggregate;
Recent Activity had no persisted event log, so it was built as a
*derived* read from existing `Post`/`Site` columns instead; Quick
Actions turned out to be two genuinely different concerns (two
actions with real destinations, two with none) wearing one component;
AI Assistant Preview had nothing real to migrate to at all and stayed
mocked. *Why this matters for an interview answer:* "remove all the
mocks" is rarely a single mechanical operation — the right questions
per instance are "does real data already exist," "can real data be
*derived* without a new table," and "is there honestly nothing real
here yet," and each answer implies a different amount of new backend
work, not a uniform migration script.

**2. Choosing to derive Recent Activity from existing columns instead
of building an event-log table.** *Problem:* a "real" activity feed
naturally suggests persisting every event (post published, draft
created, site connected) as its own row, the way a real audit log
would. *Chosen solution:* no new table — `DashboardService::recentActivity()`
queries `Post`/`Site` directly (`published_at`, `created_at`,
`last_connected_at`) and merges three result sets in application code.
*Why this approach:* those timestamps already exist and are already
correct; a dedicated events table would duplicate data that's already
authoritative elsewhere, for a feed that's read far more often than
any hypothetical event volume would justify optimizing for. The
explicit trade-off (three queries instead of one, merged in PHP) was
named as accepted debt rather than solved prematurely — see Future
Backlog.

**3. Treating "should it become real" and "should it stay a
placeholder" as equally legitimate audit outcomes, in the same
review.** *Problem:* a milestone framed around "eliminate mocks" can
create pressure to make everything real, including things with no
real product decision behind them yet. *Chosen solution:* Settings
became real-but-read-only (genuine workspace/user data, no invented
editable-preferences feature); Notifications and the AI domain were
reviewed and explicitly left alone, since inventing either now would
mean guessing a product decision (what counts as a notification; what
an AI provider integration looks like) nobody has made yet. *Why this
matters for an interview answer:* a technical-debt-reduction milestone
succeeding doesn't mean shipping the maximum amount of "real" — it
means every remaining mock or placeholder has a written reason to
still be one, the same discipline this project already applied to
Registration (Milestone 8) and the "AI Jobs" table (Milestone 7).

### Milestone 11 (Background Jobs & Queue Platform)

**1. Putting the `Syncing` status transition in two places, deliberately,
not redundantly.** *Problem:* a queued job doesn't start executing the
instant it's dispatched — there can be real queue lag between "the
controller returned" and "a worker actually picked this up." A status
transition set only inside the job would leave the UI showing a stale
"Connected" badge during that gap. *Chosen solution:* the controller
sets `Syncing` synchronously, before dispatch, for instant feedback;
`ContentSyncService::sync()` *also* sets `Syncing` at its own start,
so a retried attempt (after a transient failure) re-enters a
consistent "in progress" state rather than lingering on a stale
`Error` badge between the failed attempt and the next retry. *Why
this matters for an interview answer:* two writes to the same field
from two different layers looks like duplication at first glance —
the actual test is whether each one is covering a distinct real
window of time the other can't see, which here it is.

**2. Verifying a security property instead of assuming it.**
*Problem:* the brief's Security section asked to review "queue
payloads, serialized models, credential handling" — a WordPress
Application Password is exactly the kind of secret that shouldn't
leak into a job's serialized queue payload. *Investigation:* traced
Laravel's `SerializesModels` trait behavior directly rather than
trusting framework folklore — it serializes an Eloquent model as a
class name + primary key only, and re-fetches the full model from the
database on unserialize. Since `Site.credential` is a lazily-loaded
relation never eager-loaded onto the job's `Site` property, the
encrypted `application_password` genuinely never enters the `jobs`
table's payload column at any point. *Why this matters for an
interview answer:* "the framework handles that" is a claim worth
actually checking once, for anything security-relevant, rather than
carried forward as an assumption — the check here took minutes and
turned a plausible-sounding property into a verified one.

**3. Choosing not to wire the existing synchronous action to the new
job, and writing down why.** *Problem:* `RefreshSiteMetadataJob` was
built and the temptation was to immediately point the existing manual
"Refresh Metadata" button at it too, since the job already exists.
*Chosen solution:* left the button synchronous. It's a single, fast,
bounded request (unlike content sync's paginated fetch) — a user
clicking it wants an answer in a couple of seconds, and async would
add UX latency (a queue round-trip) for no real benefit. The job is
genuinely reused, just by the new Scheduler task instead. *Why this
approach:* "we built a reusable job, so use it everywhere" is a false
economy when the two call sites have genuinely different latency
requirements — reuse should follow the actual need, not the existence
of the abstraction.

### Milestone 12 (Media Platform & Storage)

**1. A DB-level unique constraint that looked correct, caught silently
breaking a real workflow by the test suite itself, not by inspection.**
*Problem:* added genuine unique constraints on the polymorphic
attachment slot (`mediable_type`/`mediable_id`/`collection`) and on
`(site_id, source_id)` — the obviously "correct" way to enforce "one
featured image per post" and "don't re-download the same WordPress
media twice" at the database layer. A test for replacing a post's
featured image on re-sync then failed with a real `QueryException`.
*Investigation:* `SoftDeletes` marks a row `deleted_at` but leaves it
physically present — a unique index has no concept of that column, so
soft-deleting the old attachment and inserting the replacement
collided on the same constraint. Checked whether this was a one-off:
`posts`' own `(site_id, wordpress_post_id)` unique index carries the
identical tradeoff, coexisting with its own `SoftDeletes` since
Milestone 10, apparently never exercised the same way. *Chosen
solution:* removed both new unique constraints, kept them as plain
indexes, and enforced the actual invariants in the service/mapper
layer where the business decision already lived. *Why this matters for
an interview answer:* a schema constraint that looks obviously correct
in isolation can still be wrong once a cross-cutting concern
(`SoftDeletes`) interacts with it — and the discipline of asking "does
this same shape already exist elsewhere, unexercised" turned a
one-off fix into a documented, project-wide risk instead of a
quietly-patched local bug.

**2. A silent route-model-binding failure, not an error, from a
one-word English pluralization mismatch.** *Problem:* every
`GET`/`PATCH`/`DELETE /media/{media}` request failed authorization with
"call to a member function `hasMember()` on null" — the resolved model
had an *empty* attributes array, not a missing one, meaning no
exception, no 404, no obvious signal something was wrong before the
policy check ran. *Investigation:* `Route::apiResource('media', ...)`
singularizes the resource name for its URI parameter — and English
"media" is already the plural of "medium," so Laravel generated
`{medium}`, not `{media}`. The controller's methods type-hinted `Media
$media` (matching the model, not the mis-singularized route
parameter), so implicit binding's name-matching silently failed and
Laravel's container just constructed a blank `new Media()` for the
type-hinted parameter instead of raising any error. *Chosen solution:*
`->parameters(['media' => 'media'])` on the resource route, forcing
the URI parameter to match the controller's actual variable name.
*Why this matters for an interview answer:* "route model binding
failed" doesn't always mean a 404 or a thrown exception — a name
mismatch between convention-generated routing and a hand-written
controller signature can produce a fully-constructed, silently-empty
object instead, which is a much harder failure mode to recognize from
the symptom alone (a null-pointer-style error deep inside unrelated
business logic) without tracing back to the actual route definition.

**3. Introducing a mandatory Architecture Drift Review before writing
any code, and finding it earned its cost immediately.** *Problem:*
five milestones into extending an increasingly complex system, the
risk of quietly duplicating an existing abstraction or violating an
already-accepted decision grows with every addition — and nothing in
the existing workflow forced that check before implementation started.
*Chosen solution:* added a dedicated review step, run first, checking
specifically for duplicate services, overlapping responsibilities, and
whether existing decisions still held. *Outcome:* confirmed the new
domain was genuinely greenfield (no prior partial implementation to
build on or conflict with) and caught a real naming-adjacent
ambiguity (`Site.storage_used_mb` vs. this milestone's own storage
concern) before it could cause confusion later, resolved by
documentation in minutes rather than by a future session's
investigation. *Why this matters for an interview answer:* a process
change is only worth adopting permanently if it catches something a
skilled engineer moving fast would plausibly have missed — this one
did, on its very first run, which is the actual argument for keeping
it as a standing step rather than a one-off exercise.

### Milestone 13 (GraphQL Layer)

**1. Scoping a new technology by what it should *not* touch, before
writing any code.** *Problem:* the brief for this milestone was
deliberately terse ("GraphQL where it adds real value... not a
wholesale replacement") rather than prescriptive — and a schema-first
GraphQL package makes it genuinely easy to expose every model as a
queryable type with minimal code, which is exactly how a "just GraphQL
the whole API" scope creep happens on a real project. *Chosen
solution:* used the Architecture Drift Review to explicitly evaluate
and reject exposing Sites/Posts as GraphQL types, in writing, before
implementation — not because it was hard, but because both already
have complete, tested, policy-enforced REST CRUD, and a second path to
the same data would duplicate proven capability instead of adding
value. *Why this matters for an interview answer:* the interesting
engineering decision in this milestone wasn't "how do I add GraphQL,"
it was "what is GraphQL specifically for, here" — and answering that
before writing a schema is what kept a two-query addition from
becoming a parallel API surface to maintain forever after.

**2. Recognizing a previously-documented failure pattern in under a
minute, instead of re-debugging it from scratch.** *Problem:* a newly
`composer require`'d package didn't appear in Laravel's package
discovery output at all, and its config/schema files had nothing to
publish — a confusing, silent failure with no error message pointing
at a cause. *Investigation:* recognized the shape of the problem
immediately from a previously-documented incident (Milestone 6's
Engineering Journal entry on OneDrive-synced-path cache staleness) —
checked `bootstrap/cache/services.php`'s timestamp, confirmed it
predated the new package's installation, deleted it, and package
discovery worked immediately. *Why this matters for an interview
answer:* the actual return on writing investigation entries down isn't
having a record for its own sake — it's cutting a second occurrence of
the same failure from a multi-step debugging session down to a
30-second recognition, which is exactly what happened here.

**3. A framework semantics assumption that was backwards, caught by
testing the real thing instead of trusting the type system.**
*Problem:* `typecheck`, `lint`, and `build` all passed cleanly on code
that crashed immediately in a real browser. *Investigation:* GraphQL
enum fields serialize over the wire using their schema-defined NAME
(`POST_PUBLISHED`), not the internal value a `@enum(value: ...)`
directive maps them to (`post-published`) — the opposite of what
seemed like the directive's obvious purpose at a glance. Static checks
couldn't catch this because TypeScript trusted the type annotation I
wrote, not the actual runtime value the API returned; only running the
real login-to-Dashboard flow in a real browser surfaced the mismatch,
as a React "invalid element type" crash inside a component that had
worked unmodified since Milestone 5. *Chosen solution:* translated the
wire-format enum name back to the internal value at the single
boundary where GraphQL data enters the frontend, so no downstream
component needed to know the wire format ever changed. *Why this
matters for an interview answer:* "all checks passed" is not the same
claim as "this works" — a type system verifies internal consistency
against the types you told it were true, and a genuinely wrong
assumption about an external system's wire format sails straight
through it undetected until something actually calls the API.

### Milestone 14 (AI-Assisted Content Generation)

**1. Designing a provider abstraction, then watching it actually earn
its cost mid-milestone, not hypothetically.** *Problem:* the original
scope was a single AI provider; partway through implementation, the
requirement changed to "support a second provider, selectable, without
losing the first." *Chosen solution:* the integration was already
shaped around a one-method `AiClientContract` (deliberately mirroring
this project's own `WordPressClientContract` precedent — "one contract
method" — from Milestone 9), so adding the second provider meant one
new class implementing the existing interface and one `match` arm in
a container-binding closure. Zero changes to the job, the controller,
the request/response layer, or the frontend. *Why this matters for an
interview answer:* the textbook argument for the Strategy pattern is
usually illustrated with a hypothetical; this is the rarer case of
watching the actual mid-project requirement change land on an
abstraction built for exactly that shape of change, and confirming it
by counting the diff — one new file, one new binding branch, nothing
else touched.

**2. Distinguishing "the credential is wrong" from "the request is
wrong" from "the account is out of quota" — three different failure
modes that looked identical from the outside.** *Problem:* a live
integration test against a real external API returned a `404`, which
is normally a routing/resource-not-found signal, not a credentials
signal. *Investigation:* rather than assume the API key was the
problem (a reasonable first guess, especially given its unusual
format), fetched the provider's own current API documentation in the
same session and confirmed the request shape matched exactly, then
ran a minimal, isolated request directly against the live API and read
the *provider's own error message* — which named the specific cause
(a deprecated model, not a credential issue) — then probed several
alternative model identifiers against the same key and used a `429`
response (which only happens after successful authentication) as
positive proof the credential was valid all along. *Chosen solution:*
switched the default model and documented both findings, rather than
declaring the integration "possibly broken" and moving on. *Why this
matters for an interview answer:* three different failure classes —
bad credential, bad request, exhausted quota — can all present as an
opaque HTTP error from the outside; the discipline of isolating each
one with a targeted, minimal reproduction (and reading what the
external system actually says, not just its status code) is what
turns "the API doesn't work" into a precise, defensible root cause.

**3. Choosing what "verified" means when the last mile is outside your
control.** *Problem:* full success-path verification depended on an
external account's quota, which was exhausted partway through testing
— genuinely outside this session's control to fix. *Chosen solution:*
rather than either declaring the milestone blocked or quietly skipping
verification, precisely scoped what *had* been proven live (request
format, model accessibility, authentication, async job processing,
retry/backoff behavior, typed error mapping, and the frontend's error-
handling UI, all against the real external API) versus what remained
covered only by an automated integration test (the successful-
completion render path), and documented the boundary explicitly rather
than blurring it. *Why this matters for an interview answer:* "fully
verified" and "verified everything within this session's actual
control, with the remaining gap named precisely" are different claims,
and conflating them is how a real, if narrow, coverage gap quietly
becomes an unstated assumption in a later session.

### Milestone 15 (Docker Development Environment)

**1. Evaluating a "just use the standard tool" option by reading its
actual source, not its reputation.** *Problem:* the brief explicitly
required weighing Laravel's own official Docker tool against a
hand-written setup, with an explicit instruction not to default to the
official one just because it exists. *Investigation:* rather than
reason from general knowledge of what the tool is "supposed" to do,
read its actual published runtime Dockerfile and process-supervisor
configuration directly. Found it ran the framework's single-threaded
built-in development server instead of the production-grade application
server this milestone's brief specifically required, had no reverse
proxy at all, and had no background-worker or scheduled-task support
configured out of the box — three direct conflicts with named
requirements, not stylistic preferences. *Chosen solution:* a small,
purpose-built alternative instead, with the reasoning for each rejected
piece written down before writing any configuration. *Why this matters
for an interview answer:* "should we use the standard tool" is a
legitimate question that deserves a real investigation, not a reflexive
yes — and the investigation is what makes the eventual answer (even a
"no") defensible instead of just contrarian.

**2. A misleading error message that pointed at the wrong layer
entirely.** *Problem:* a live browser check failed with what looked
unambiguously like a cross-origin (CORS) configuration error — the
browser's own console named CORS specifically. *Investigation:* rather
than start editing CORS configuration, fetched the actual server
response directly and found a `500` error underneath, from a completely
unrelated cause (a filesystem permission failure) that happened to occur
early enough in the request lifecycle that the response never got its
CORS headers attached at all — a browser reports *that* as a CORS
failure, since from its perspective the required header is simply
missing, regardless of why. *Chosen solution:* traced the actual
`500`'s stack trace to its root cause (a container filesystem
permission problem, unrelated to networking entirely) and fixed that
instead of touching any CORS configuration. *Why this matters for an
interview answer:* a browser's own error classification is a
description of a *symptom* at the browser's vantage point, not a
diagnosis — the same missing-response-header can have causes with
nothing to do with the category the browser puts it in, and treating
the browser's label as the root cause would have led to "fixing" an
already-correct CORS configuration while leaving the real defect in
place.

**3. Distinguishing "the environment is slow" from "the environment is
broken" by isolating the actual variable.** *Problem:* a real user flow
(logging in) silently failed to complete inside the containerized
environment, with zero errors logged anywhere — the kind of symptom
that's tempting to misdiagnose as "flaky" and retry away. *Investigation:*
instrumented the exact sequence of network activity and console output
around the failure and found a development-server hot-reload event
firing in the middle of the in-flight action, which remounted part of
the page and silently discarded the pending post-action navigation that
was about to run. Rather than treat "sometimes it works, sometimes it
doesn't" as inherent flakiness, isolated *why* the hot-reload was firing
at all — tracing it to the development server's file-change watcher
scanning a completely unrelated, large directory tree (a second
application's dependency folder) it had no reason to watch, made worse
by unreliable native file-change notification across the container
boundary. *Chosen solution:* two independent fixes addressing each
layer of the actual cause — excluding the irrelevant directory from the
watched path, and switching to a deterministic (if slightly more
resource-intensive) file-watching strategy — rather than papering over
the symptom with longer waits or retries. *Why this matters for an
interview answer:* intermittent-seeming failures are often fully
deterministic once the *actual* triggering condition is isolated instead
of assumed; "add a longer timeout" fixes the symptom's visibility, not
the defect, and this one had a measurable, fixable root cause the whole
time.

### Milestone 16 (Frontend Testing & CI/CD)

**1. Choosing test scope deliberately, and writing down what was
excluded and why.** *Problem:* "add tests" has no natural stopping
point — a codebase this size could absorb hundreds of component tests
of steadily diminishing value. *Chosen solution:* picked five files
specifically because each demonstrates a distinct testing technique
against real logic (form validation and error branching, a multi-state
UI driven by asynchronous data, pure-function mapping, a hook's
conditional-fetch behavior) and named, explicitly, the much larger set
of presentational components deliberately left untested because they
have no branching logic to verify. *Why this matters for an interview
answer:* "what did you decide *not* to test, and why" is a more
revealing question than "what's your coverage percentage" — a coverage
number can't distinguish thorough judgment from mechanical
box-checking, and being able to state the boundary and its reasoning
demonstrates the former.

**2. A dependency conflict that surfaced a real, current ecosystem
transition — and resolving it without hiding the conflict.** *Problem:*
installing the standard React plugin for the new test runner failed
outright on a peer-dependency conflict between two major versions of a
transitive build tool. *Investigation:* rather than force the install
past the warning (`--legacy-peer-deps`/`--force`, both of which accept
whatever the resolver produces without verifying it actually works),
inspected what changed between the conflicting package's recent major
versions and pinned the last release before the conflicting optional
dependency was introduced. *Why this matters for an interview answer:*
a peer-dependency error is the package manager correctly refusing to
guess — overriding it blindly trades a visible, fixable problem for an
invisible, possibly-broken one; the right response is almost always to
understand *why* the conflict exists, not to suppress the check.

**3. Refusing to ship a CI gate that would fail on its own first
run.** *Problem:* adding an automated code-style check to continuous
integration is only trustworthy if it starts passing — a check that's
red from day one teaches a team to treat "CI is red" as background
noise rather than a signal. *Investigation:* running the style checker
across the entire codebase (rather than only files touched by recent
work, which is what every previous check had actually run) surfaced
several pre-existing violations nobody had caused recently and nobody
had been asked to fix. *Chosen solution:* fixed all of them, verified
the full test suite still passed, *then* added the check to the new CI
configuration — deliberately sequenced so the very first automated run
of the new gate would be green. *Why this matters for an interview
answer:* introducing a new quality gate is itself a migration, not just
a configuration change, and treating it that way (fix first, enforce
second) is what makes the gate something people trust rather than
something they route around.

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

### Milestone 8 (Authentication & Authorization)

- Implemented cookie/session-based SPA authentication (Laravel
  Sanctum) with CSRF protection, session-fixation mitigation, and
  rate-limited login, deliberately choosing session cookies over
  JWTs/bearer tokens to eliminate an entire class of XSS-based token
  theft.
- Designed and built a request-scoped "Current Workspace Resolver"
  architecture (a resolver service, middleware, and a request-scoped
  container binding) so multi-tenant authorization logic lives in one
  place and a future workspace-switching feature requires no
  controller changes.
- Identified and fixed two real cross-tenant data-isolation
  vulnerabilities during architecture review — an unscoped dashboard
  aggregation query and unauthorized tenant-ID filters on two index
  endpoints — before they shipped, closing both with regression tests
  proving isolation.
- Wired existing, previously-unused authorization policies into live
  API endpoints and resolved a known N+1 authorization risk
  architecturally (eliminating per-row permission checks entirely for
  list endpoints) rather than with a caching workaround.
- Grew the backend automated test suite from 38 to 57 passing tests,
  adding dedicated authentication and cross-tenant isolation coverage,
  and fixed a real gap in centralized exception handling
  (`AuthorizationException` wasn't mapped to the API's error envelope)
  found while writing those tests.
- Built the full frontend authentication experience (protected
  routing with intended-destination preservation, session-aware
  TanStack Query state, centralized CSRF/credential handling) and
  verified the entire login/logout/session lifecycle end-to-end in a
  real browser against a production build.

### Milestone 9 (WordPress Integration Platform)

- Designed and built a dedicated external-integration architecture
  (contract, HTTP client, authenticator, DTOs, typed exceptions) for
  connecting to third-party WordPress REST APIs via Application
  Passwords, with retry/timeout handling and graceful degradation
  against partial API failures.
- Identified and mitigated a server-side request forgery (SSRF) risk
  in a "connect to a user-supplied URL" feature before implementation
  — built and tested a URL-safety guard blocking private/internal
  network addresses, verified via assertions that no outbound request
  is ever attempted for an unsafe target.
- Implemented encrypted credential storage with defense-in-depth
  (a dedicated database table never touched by the API's serialization
  layer, field-level encryption, and hidden-attribute protection),
  verified directly in tests that plaintext credentials never reach
  either the database or an API response.
- Extended a multi-tenant authorization system to a new external-
  integration feature without modifying its core policies, and closed
  a data-integrity gap by moving a previously client-settable resource
  status field to be exclusively server-derived from verified external
  data.
- Grew the backend automated test suite to 73 passing tests, entirely
  mocking third-party API calls (no live external dependency in CI),
  and found/fixed a real bug in exception-handling precedence
  (a retry mechanism's default behavior was silently overriding custom
  error handling) during test development.
- Built the application's first dynamic/nested frontend route and used
  it to resolve a previously-deferred navigation UX gap (parent-route
  highlighting for nested pages).

### Milestone 10 (Content Synchronization Platform)

- Designed and built a generic, extensible content-synchronization
  engine (a mapper-contract abstraction over a fetch/map/hash/upsert
  orchestrator) so a third-party integration's synchronization logic
  is reusable across future content types without rewriting the
  orchestration layer — validated by implementing the first concrete
  content type (WordPress posts) against it.
- Implemented idempotent, hash-based change detection for external
  data synchronization (not a timestamp heuristic alone), preventing
  duplicate imports on repeat sync runs and correctly distinguishing
  "no change" from "changed" even when an upstream system's own
  modification timestamp can't be fully trusted — verified directly in
  tests proving zero duplicate rows and exactly one update on a
  detected change.
- Resolved a real schema-design question (whether externally-synced
  content and internally-authored content belong in one table or two)
  by tracing every existing and future consumer of the affected model,
  extending an existing production table rather than introducing a
  parallel one and the consumer-side duplication that would have
  followed.
- Extended an existing typed external-API client with a new operation
  (paginated collection fetching) by refactoring shared response-
  validation logic into a single reusable method, avoiding duplicated
  HTTP-handling code across two different request shapes.
- Reused an existing, already-tenant-scoped REST endpoint for a new
  feature's read path instead of building a parallel one, after
  tracing that the existing endpoint's query logic already covered the
  new requirement — a deliberate "extend, don't duplicate" call over
  literally following an example route list.
- Grew the backend automated test suite to 83 passing tests, including
  dedicated coverage for idempotency, update detection, duplicate-
  import prevention, and authorization; verified the feature's
  external-failure path live in a real browser against a production
  build (a genuinely unreachable external host), confirming a
  synchronous failure correctly surfaces through the same error-display
  path an unrelated, previously-built feature already used.

### Milestone 10.1 (API Completion & Frontend Migration)

- Audited every remaining mock data source in a production frontend
  application and converted four of six to real, tested backend
  endpoints (traffic analytics, system health, activity feed, content
  drafts), while deliberately and explicitly documenting why the
  remaining two stay mocked or partially mocked — closing a
  technical-debt item flagged across three prior engineering reviews
  without over-building unproven functionality.
- Designed and implemented a derived activity-feed endpoint without
  introducing a new database table, composing existing timestamped
  columns across two models into a unified, correctly-sorted feed —
  avoiding both a stale mock and a premature audit-log schema.
- Extended a real-time analytics aggregation query (built for a
  different dashboard metric) to power a second, independent chart
  widget with a different time-range shape, reusing the same
  underlying historical data table rather than introducing a parallel
  data source.
- Extracted a duplicated health-check routine into a shared, reusable
  service consumed by two independent endpoints, closing a real code-
  duplication finding surfaced during a deliberate technical-debt
  review pass rather than an ad hoc refactor.
- Extended an existing, already-tested REST endpoint's filtering
  capability (a single new accepted query value reusing an existing
  Eloquent scope) instead of building a parallel endpoint, keeping the
  authorization and tenant-isolation guarantees already proven for
  that endpoint intact for the new use case.
- Ran an automated accessibility audit (axe-core) against two pages
  carrying entirely new real-data-driven content and confirmed zero
  violations, and grew the backend automated test suite to 95 passing
  tests with zero regressions across an eight-file backend change set.

### Milestone 11 (Background Jobs & Queue Platform)

- Designed and implemented a production-grade asynchronous job
  platform on Laravel's queue system — converted a previously
  synchronous, request-blocking external-API integration into a
  dispatch-and-poll architecture with configured retry limits,
  exponential backoff, per-resource job uniqueness (preventing
  duplicate concurrent processing of the same tenant resource), and
  dedicated failure-handling logic that updates domain state
  correctly after retries are exhausted.
- Built a reusable job abstraction (a shared contract-driven pattern
  across two job classes) explicitly designed for extension by future
  consumers (AI generation, notifications, scheduled maintenance)
  without requiring architectural changes — validated by immediately
  reusing it for a second, unrelated background operation via the
  Laravel Scheduler.
- Replaced a hardcoded placeholder operational metric with a real one,
  querying live queue-infrastructure tables to report accurate
  pending/failed job counts and a derived health status — verified
  end-to-end against a genuinely failed background job in a live
  browser session, not just asserted in isolation.
- Performed and documented a targeted security review of the new
  asynchronous data path (serialized job payloads, credential
  handling, tenant isolation across worker execution) — verified
  directly, by reading framework internals, that an encrypted
  third-party credential never enters the queue's persisted payload,
  rather than assuming a framework convention held.
- Wrote automated tests exercising real queue behavior (job
  uniqueness enforced via the real cache-lock mechanism, real pending/
  failed job counts read from the actual queue tables) rather than
  only mocking the queue away, catching real configuration correctness
  a fully-faked test suite would have missed. Grew the backend
  automated test suite to 103 passing tests with zero regressions.
- Made and documented a deliberate reuse-scope decision — built a
  second reusable job but declined to wire it into an existing
  synchronous user-facing action where async would add latency without
  real benefit, resisting the temptation to force-fit a new
  abstraction everywhere it technically could apply.

### Milestone 12 (Media Platform & Storage)

- Designed and built a reusable, polymorphically-attachable media/file
  storage domain (Laravel) serving multiple current and future
  producers (third-party API-sourced assets, direct user uploads) from
  one schema and one service, backed by a disk abstraction requiring
  only a configuration change — not a code change — to migrate to
  cloud object storage.
- Implemented content-hash-based file deduplication at the storage
  layer, preventing redundant disk writes across independent uploads
  of identical content, verified directly by asserting two separate
  uploads share one physical file on disk.
- Diagnosed and fixed a database constraint conflicting with an
  existing soft-delete pattern, caught by the automated test suite
  before release — traced the root cause to a general interaction
  between unique indexes and soft-deletion (not specific to the new
  feature), identified an identical, previously unexercised risk
  already present elsewhere in the schema, and resolved both by moving
  the invariant into the application layer with full documentation of
  the tradeoff.
- Diagnosed a silent HTTP routing failure (a convention-based
  framework tool auto-generating a URL parameter name that didn't
  match the controller's own signature) that produced a fully-formed
  but empty object instead of any error — traced through the
  framework's own resolution internals rather than guessing, and fixed
  with a one-line, explicit route configuration.
- Extended an existing external-API integration and its established
  asynchronous job pattern to a new capability (binary file download)
  without modifying either's core architecture, including reusing an
  existing security control (an SSRF guard built for a different
  feature) for a new outbound request path handling third-party-
  supplied URLs.
- Introduced a new mandatory pre-implementation architecture review
  step for the project, and demonstrated its value on first use by
  catching a real (if minor) documentation gap before implementation
  began, rather than treating the process addition as a formality.
- Found and fixed a genuine WCAG AA color-contrast failure during
  interactive accessibility testing (not just a static audit) — a
  component-composition interaction (a semi-transparent shared
  background under a themed button) invisible from either piece in
  isolation — and resolved it by reworking the interaction rather than
  overriding shared design-system tokens. Grew the backend automated
  test suite to 120 passing tests with zero regressions.

### Milestone 13 (GraphQL Layer)

- Designed and implemented a scoped GraphQL API layer (Laravel,
  schema-first) alongside an existing REST API, deliberately limited
  to a single real aggregation use case rather than a general-purpose
  replacement — consolidating four separate REST round-trips into two
  GraphQL requests for a dashboard-style UI, with resolvers delegating
  to existing, already-tested service-layer methods rather than
  duplicating business logic in a second transport.
- Made and documented a deliberate scope-boundary decision under real
  pressure to expand it — evaluated exposing two additional core
  resources through the new GraphQL layer, using a formal architecture
  review step, and rejected it in writing because both already had
  complete, tested, authorization-enforced REST coverage, avoiding a
  duplicate API surface with no corresponding product need.
- Diagnosed a silent third-party package registration failure
  (framework-level dependency-injection cache staleness) immediately
  by recognizing it as a recurrence of a previously-documented
  incident, cutting what could have been a multi-step debugging
  session down to a direct fix.
- Found and fixed a genuine framework semantics defect that passed
  type-checking, linting, and a production build cleanly but crashed
  in the real application — a serialization-format mismatch between
  what a schema-validation directive appeared to guarantee and what
  the wire protocol actually sent — caught specifically because the
  verification process included exercising the real login-to-dashboard
  flow in an actual browser, not just static analysis.
- Preserved a zero-component-change migration discipline across a full
  data-transport swap (REST to GraphQL) for four existing UI widgets,
  isolating the change entirely to each widget's data-fetching hook —
  and removed five now-unused frontend files as part of the same
  change, rather than leaving superseded code in place.
- Extended a shared authentication/multi-tenancy middleware stack to a
  new API transport without building a parallel implementation of
  either concern, and grew the backend automated test suite to 127
  passing tests with zero regressions, including dedicated coverage
  for cross-tenant data isolation and schema-level input validation on
  the new API surface.

### Milestone 14 (AI-Assisted Content Generation)

- Designed and implemented a provider-agnostic AI integration layer
  (Laravel) supporting two interchangeable large-language-model
  providers (Anthropic Claude via its official SDK, Google Gemini via
  a hand-rolled REST client) behind a single one-method contract,
  selected at runtime by configuration — absorbing a real mid-project
  requirement change (adding the second provider) as a pure addition
  with zero changes to any calling code.
- Added the schema this project's own architecture record had
  explicitly deferred a domain model milestone earlier, designing it
  against two real provider integrations instead of guessing at a
  shape ahead of time — closing a named, tracked technical-debt item
  rather than letting it age indefinitely.
- Extended an existing asynchronous job-queue platform to a new
  workload (external AI generation) rather than building a second
  queueing mechanism, including a deliberate split between
  immediately-failing and automatically-retried failure modes based on
  whether a retry could plausibly change the outcome.
- Mapped two structurally different external error taxonomies (a typed
  SDK exception hierarchy; raw HTTP status codes and a JSON error
  envelope) onto one consistent internal exception hierarchy, so
  application code and API consumers never need provider-specific
  error handling.
- Diagnosed a live third-party API failure through direct
  experimentation against the real service rather than assumption —
  distinguished a deprecated-model error from a credential problem by
  probing multiple model identifiers and interpreting a rate-limit
  response as proof of successful authentication — without ever
  exposing the credential itself in any log, tool output, or
  documentation artifact.
- Defined and documented a precise boundary between live-verified and
  test-only-verified functionality when external factors (a
  third-party account's usage quota) prevented completing live
  verification, rather than overstating or silently omitting the gap.
  Grew the backend automated test suite to 142 passing tests with zero
  regressions, including dedicated coverage for the retryable/
  non-retryable failure split and the provider-selection mechanism
  itself.

### Milestone 15 (Docker Development Environment)

- Designed and implemented a multi-container local development
  environment (reverse proxy, application server, background worker,
  scheduled-task runner, frontend dev server) using an industry-standard
  container orchestration tool, evaluated against the ecosystem's own
  official tooling for the same framework and chosen deliberately over
  it based on a direct comparison against the project's actual
  requirements rather than the official tool's default reputation.
- Diagnosed and eliminated a recurring filesystem-caching defect class
  (previously documented and independently rediscovered twice already
  in this project's history) by construction — architecting the
  container storage strategy so the specific failure mode could not
  recur, rather than adding a workaround for each new location it might
  appear.
- Diagnosed a misleading cross-origin error report back to its true,
  unrelated root cause (a container filesystem permission fault) by
  reading the actual underlying server response instead of trusting the
  browser's own error classification, then fixed the real defect at its
  source.
- Diagnosed and resolved a silent authentication-flow failure caused by
  a development-server hot-reload event interrupting an in-flight user
  action — traced through instrumented network and console logging to
  its actual root cause (an overly broad file-watch scope crossing
  container boundaries) rather than accepted as environmental flakiness,
  and fixed with two complementary, targeted changes.
- Performed a genuine clean-machine validation (not just a configuration
  review) of the full environment — a from-scratch bootstrap sequence,
  the complete backend automated test suite, and an end-to-end browser
  session covering every major application area — catching and fixing
  four real, independently-verified defects before considering the work
  complete, including one that reduced a core developer-facing
  interaction's latency by roughly 90%.

### Milestone 16 (Frontend Testing & CI/CD)

- Established the project's first frontend automated test suite,
  deliberately scoped to critical user flows and pure logic rather than
  exhaustive component coverage, and documented the specific reasoning
  and boundary for that scope decision rather than treating "more tests"
  as an unqualified good.
- Diagnosed and resolved a real peer-dependency version conflict between
  a testing tool and an existing project dependency by identifying the
  specific transitive change that introduced the conflict and pinning
  around it, rather than suppressing the package manager's conflict
  detection.
- Designed and implemented a two-job continuous integration pipeline
  covering both halves of a full-stack application (static analysis,
  automated tests, and production build verification for each),
  deliberately choosing native CI runners over an available
  containerized environment after evaluating that the containerized
  setup was scoped to a different concern.
- Audited the full codebase against an automated code-style tool for
  the first time (previous checks had only ever covered recently-changed
  files) and resolved every pre-existing violation found before
  introducing that tool as a required automated check — ensuring a new
  quality gate started in a passing state rather than immediately
  generating noise.

---

## 2026-07-22 — A `beforeEach()` that silently never ran, found while building a DNS-SSRF test

**Problem.** Milestone 19's DNS-resolution SSRF fix
(`docs/adr/0017-cloud-deployment-and-security-hardening.md`) needed a
fake `DnsResolver` bound for the whole test suite, the same reason
`Http::fake()` is used everywhere — otherwise every test connecting a
site via a hostname would make a genuine DNS lookup. Added a
standalone `beforeEach(fn () => fakeDnsResolution())` at the top level
of `tests/Pest.php`. The new dedicated SSRF test passed reliably. The
*existing* test suite did not: `php artisan test` intermittently failed
on unrelated tests (`ContentSyncTest`, different tests on different
runs) with `UrlSafetyValidator`'s own "resolves to a private or
reserved network address" error — for hostnames the test never
supplied, generated by `Faker::domainName()`.

**First hypothesis (wrong).** Assumed Laravel's `RefreshDatabase` +
in-memory SQLite optimization was reusing the same Application
container across tests, letting one test's container binding leak into
the next. Read `Illuminate\Foundation\Testing\TestCase::setUp()`
directly — it calls `createApplication()` fresh for every test,
unconditionally. Container bindings can't be leaking across tests this
way; the premise was wrong.

**Investigation.** Instrumented both the real `DnsResolver::resolve()`
and the test helper `fakeDnsResolution()` with debug logging
(container object ID, whether `queue.worker` was bound) and ran the
suite with a clean log each time. The evidence was unambiguous: the
**real** resolver was being called — 44 times across a full suite run,
each for a genuine Faker-generated hostname like `hane.biz` or
`tillman.com` — and **zero** `BINDING_FAKE_DNS_RESOLVER` log lines
appeared anywhere. The global `beforeEach()` wasn't leaking state
between tests; it was never running at all, for any test, anywhere.

Read Pest's own source rather than guess further.
`Pest\PendingCalls\BeforeEachCall::__destruct()` registers the hook via
`$this->testSuite->beforeEach->set($this->filename, ...)`, where
`$filename = Backtrace::file()` — the file `beforeEach()` was physically
called from (`tests/Pest.php`). `Pest\Repositories\
BeforeEachRepository::get(string $filename)` looks up hooks by
**exact filename**, no directory-based cascading. A bare top-level
`beforeEach()` declared in `Pest.php` only ever fires for tests
declared in `Pest.php` itself — which has none. Chaining `.in('Feature')`
onto it (the fix's first attempt) didn't help either: `.in()` only
exists on `Pest\PendingCalls\UsesCall` (the object `pest()->extend(...)`
returns), not on `BeforeEachCall` — calling it there silently falls
through `__call()`'s fallback branch instead of scoping anything.

**Decision.** `UsesCall` has its own `beforeEach()` method, designed
specifically to combine with the same object's `.in()` targeting.
Moved the hook to chain directly onto the existing
`pest()->extend(TestCase::class)->use(RefreshDatabase::class)` call,
before `.in('Feature')`:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => fakeDnsResolution())
    ->in('Feature');
```

**Verification.** Re-ran with the same debug instrumentation: 10
`BINDING_FAKE_DNS_RESOLVER` calls for a 10-test file (one per test, as
expected), 0 real `DnsResolver` calls. Removed the debug logging, ran
the full 146-test suite five times consecutively — consistent, fast
(≈10s, down from a real, measured 20–35s when random DNS lookups were
in the mix), zero failures.

**Lessons learned.** A standalone `beforeEach()`/`afterEach()` at the
top level of `Pest.php` is not the global hook it visually resembles —
it silently scopes to nothing outside that file. The correct pattern
for a suite-wide hook is chaining `.beforeEach()` onto the same
`uses()`/`pest()->extend()` call that already does the real directory
targeting via `.in()`. The deeper lesson generalizes past this one API:
a test that passes in isolation but fails intermittently in the full
suite is a real signal, not noise to retry past — and "state leaking
between tests" and "a hook silently never registering" produce
identical *symptoms* (a value that should be mocked isn't), so the
first hypothesis (container reuse) needed direct evidence, not just
plausibility, before being ruled out. Reading the actual framework/
library source (`TestCase::setUp()`, then `BeforeEachCall`/
`BeforeEachRepository`) settled it faster than continued speculation
would have.

---

## 2026-07-20 — An empty, untracked test directory that only a real clean checkout could expose

**Problem.** The first-ever GitHub Actions run against this repository
failed on the Backend job, 27 seconds in — `php artisan test` exited
instantly with `INFO Test directory ".../backend/tests/Unit" not
found.` and exit code 2. Every step before it (checkout, PHP setup,
`composer install`, `.env` bootstrap, `pint --test`) had succeeded.

**Investigation.** `php artisan test` had passed locally on this exact
codebase moments earlier, so the immediate question was what a GitHub
Actions checkout could possibly see differently. `ls -la
backend/tests/Unit/` locally showed the directory genuinely existed —
but completely empty (0 files). `git ls-files backend/tests/Unit/`
returned nothing at all: git does not track empty directories, so this
directory had never actually been committed to the repository — not in
this milestone, not ever. It had existed on this local machine since
Milestone 6 (its own directory timestamp: `Jul 13`, this project's
first backend-foundation work), an artifact of the original Laravel
scaffold that nothing ever removed and nothing ever populated.
`phpunit.xml` still configured it as a required testsuite
(`<testsuite name="Unit"><directory>tests/Unit</directory></testsuite>`)
— a leftover from the same scaffold, since this project has never
actually written a unit test; every one of its 142 tests is
Feature-level. Locally, the phantom directory's continued physical
presence (despite carrying zero tracked content) was enough to satisfy
PHPUnit's directory-exists check. A genuinely fresh `git clone` — which
is exactly what GitHub Actions' checkout step performs — has no such
directory at all, and PHPUnit treats a *configured but missing*
testsuite directory as a hard error, not a silent skip.

**Decision.** Removed the `Unit` testsuite block from `phpunit.xml`
entirely, and its matching `pest()->extend(TestCase::class)->in('Unit')`
binding from `tests/Pest.php` — the honest fix, matching what this
project's test suite has actually been all along, rather than adding a
placeholder file just to keep an empty directory trackable for
hypothetical future unit tests nothing currently needs.

**Verification, deliberately more rigorous than "ran it again
locally."** Given the entire bug was "local state doesn't match a
clean checkout," re-running the fix only on the already-tainted local
working tree would have proven nothing. Instead: committed the fix,
then ran `git clone` (a real, second, independent copy of the
repository, not the working directory `composer install` had already
touched) into a scratch location and ran the exact CI sequence —
`composer install`, `.env` bootstrap, `key:generate`, `pint --test`,
`php artisan test` — against *that* clone. Clean pass, 142/142, before
pushing. The subsequent live GitHub Actions run confirmed it: both jobs
green.

**Lessons learned.** A local development environment accumulates state
a fresh clone never has — installed dependencies, generated caches, and
in this case a directory that existed for years without ever being
part of the actual repository. "It works locally" and "it works from a
clean checkout" are different claims, and the gap between them is
precisely what CI exists to find — this is the textbook version of that
gap, not an edge case: an artifact old enough to predate this project's
own testing conventions, invisible to every local check because every
local check ran on a machine that had quietly been carrying it for the
project's entire history.

---

## 2026-07-20 — A webpack internal crash from a stale `.next` cache, right after a dependency install

**Problem.** Immediately after installing Vitest and its supporting
packages, `npm run build` crashed with `TypeError: Cannot read
properties of undefined (reading 'length')` deep inside Next's bundled
webpack (`WasmHash._updateWithBuffer`) — an internal error with no
obvious connection to anything this milestone had actually changed (no
application source was touched before this build was run).

**Investigation.** The stack trace pointed entirely into
`next/dist/compiled/webpack/bundle5.js`, not into any project file —
consistent with a build-tool-internal state problem rather than a real
compile error in application code. This project's own Engineering
Journal already has a standing pattern for exactly this shape of
symptom: a framework build/cache directory (`.next/`,
`bootstrap/cache/`) becoming inconsistent with the code or dependencies
actually present, most often right after a dependency tree changes
underneath it.

**Decision.** Deleted `.next/` and re-ran the build without any other
change.

**Outcome.** Clean build immediately, identical route output to the
last known-good build, confirming nothing else was actually wrong.

**Lessons learned.** "A stack trace pointing entirely inside a
dependency's own bundled internals, immediately following a
`node_modules` change" is now a fast, specific pattern match for "try
deleting the build cache before investigating the error itself" — the
same standing lesson this project has drawn from `bootstrap/cache/`
staleness (Milestones 6, 13) and `.next/` reparse-point staleness
(Milestone 6, formalized into Milestone 15's named-volume strategy),
recurring here in a new error shape but the identical underlying cause
class: cached build state and current dependency state disagreeing.

---

## 2026-07-20 — A CORS error that was actually a filesystem permission fault two layers down

**Problem.** The first live browser check against the new Docker
environment failed immediately at login: the browser console reported a
CORS policy violation on the Sanctum CSRF-cookie request — a missing
`Access-Control-Allow-Origin` header.

**Investigation.** `config/cors.php`, the relevant `.env` values
(`FRONTEND_URLS`, `SANCTUM_STATEFUL_DOMAINS`), and `bootstrap/cache/`
for a stale cached config (a previously-documented failure class in this
project) all checked out correctly. Rather than keep adjusting CORS
configuration that already looked right, fetched the endpoint directly
with `curl` and inspected the actual response: a `500 Internal Server
Error`, with `tempnam(): file created in the system's temporary
directory` in the body — nothing to do with CORS at all. Laravel's CORS
middleware only attaches its headers to a response that completes
successfully through the pipeline; a `500` thrown early enough never
gets them, and the browser reports that missing header as a CORS
failure regardless of why it's missing. `storage/logs/laravel.log`
named the real cause: `SQLSTATE[HY000]: General error: 8 attempt to
write a readonly database`. `ls -la` inside the container confirmed
`storage/app` and `storage/framework/*` had built with no write bit at
all (`dr-xr-xr-x`), owned by `root`, while the actual PHP-FPM worker
process runs as `www-data`.

**Root cause.** These directories are copied into the image from the
build context via `COPY backend/ ./`. NTFS (the Windows host filesystem
Docker Desktop's build context comes from) has no Unix permission bits
for Docker to preserve — directories that existed only as empty
`.gitignore` placeholders in git came out of that translation without a
write bit, while `storage/logs` (which Laravel creates itself at
runtime, with a normal umask) was unaffected.

**Decision.** Added an explicit `chown www-data:www-data storage
bootstrap/cache && chmod -R 775 storage bootstrap/cache` in the
Dockerfile immediately after `COPY backend/ ./`, rather than trying to
coax a specific permission bit out of the build context.

**A second instance of the identical root cause, on the database
file.** Fixing the above didn't fully resolve login — the same
`SQLSTATE[HY000]... readonly database` error persisted, this time
tracing to `database/database.sqlite` itself: `www-data` still couldn't
write to it. `database/` is bind-mounted from the host (kept that way
deliberately for host-inspectability — see the ADR), so its permissions
come from Docker Desktop's host-to-container UID mapping at *runtime*,
not from anything the Dockerfile can fix at *build* time — a bind mount
doesn't exist yet when the image is built. The first attempted fix
(`chmod -R ug+rwX database` in the entrypoint script) didn't work either:
the directory's group was `root`, and `www-data` is in its own group,
not `root`, so group-write permissions never reached the process that
needed them. `chmod -R o+rwX database` (granting the "other" bucket
write access, which `www-data` always falls into regardless of group
membership) was what actually fixed it.

**Lessons learned.** A browser's own error category names the missing
symptom (a header), not necessarily the actual cause — the moment a
"CORS error" doesn't yield to CORS configuration changes, the next step
should be reading the raw server response directly, not iterating on
increasingly speculative CORS settings. Separately: `chmod ug+rw` is
only as effective as the target's actual group membership — a
permission fix that looks obviously sufficient can still miss the one
process that needed it, if that process's user isn't in the group being
granted access.

---

## 2026-07-20 — A file watcher scanning an unrelated 9,800-file directory silently broke login

**Problem.** With the CORS/permissions issues above fixed, a full
browser login flow still failed — the form submitted, the backend
returned a real `200` for the login request, and then... nothing. No
error, no redirect to the dashboard, the browser just sat on the login
page indefinitely. Separately, and initially suspected as an unrelated
issue, every first request to a given frontend route was taking
100–200+ seconds to respond.

**Investigation.** Instrumented the browser session to log every
network response and every console message. Two entries stood out
around the exact moment of the stuck login: `[Fast Refresh] rebuilding`,
followed by webpack hot-update requests for `app/layout` — the
application's root layout was being live-reloaded in the middle of an
in-flight login submission. A React component remount mid-flight
orphans any pending state tied to the old component instance, including
a queued client-side navigation call — which is exactly what silently
vanished. The question became: what was triggering a rebuild at all,
with no source file actually being edited?

**Root cause, part one.** This Next.js project has no `next.config.ts`
(intentionally zero-config since Milestone 1), so its file watcher scans
everything under its working directory by default. The frontend
container's bind mount (`.:/app`) included the *entire monorepo* —
meaning the watcher was scanning `backend/vendor`'s roughly 9,800 PHP
files on every check, alongside the actual frontend source it cared
about.

**Root cause, part two.** Separately, native filesystem change
notifications don't propagate reliably from a Windows bind mount across
the Docker Desktop VM boundary — a well-documented class of Docker
Desktop-on-Windows limitation. The dev server's native/partial watch
implementation appears to have picked up a phantom or delayed change
from that huge, irrelevant, and non-deterministically-observed
`backend/vendor` tree and triggered a rebuild it had no real reason to
trigger.

**Decision.** Two independent, complementary fixes. First, shadowed
`backend/` out of the frontend container's view entirely with an
anonymous volume (`/app/backend` in `docker-compose.yml`) — the other
services each mount `./backend` directly and are unaffected, and the
frontend genuinely never needs to see PHP source. Second, set
`WATCHPACK_POLLING=true`, trading some CPU overhead for deterministic,
reliable change detection instead of the unreliable native/partial
watch.

**Outcome, measured, not assumed.** Route compile time before either
fix: 100–200+ seconds per first hit. After excluding `backend/` alone:
15–20 seconds. Confirmed the excluded-directory fix was the dominant
factor, not general Docker-on-Windows overhead, by testing the
before/after difference directly rather than assuming both fixes
contributed equally. Login now completes and redirects correctly, with
zero console errors, on the first attempt.

**Lessons learned.** An intermittent-looking failure ("the login just
doesn't finish, no error") can be fully deterministic once the actual
triggering event is captured directly — instrumenting console/network
output turned "sometimes it doesn't work" into "there is exactly one
log line that explains this every time." Separately: a bind mount's
scope is a real performance and correctness variable, not just a
convenience decision — mounting more than an application actually needs
to see isn't free, and in a file-watching dev server it can actively
break unrelated functionality.

---

## 2026-07-16 — A live `404` that meant "deprecated model," not "invalid credential"

**Problem.** Live browser verification of the new Gemini provider
integration failed immediately: every generation attempt came back
`AI_CONFIGURATION_ERROR` from the app's own error mapping. The queue
worker's log showed the exception originating in
`AnthropicMessagesClient`, not `GeminiClient` — meaning the provider
switch itself wasn't taking effect the way it should have.

**Investigation, part one — the provider switch.** `config('ai.provider')`
resolved to `'gemini'` correctly when checked via a fresh `php artisan
tinker` process, but the *already-running* `queue:work` process was
still resolving to the Anthropic client. Laravel's queue worker boots
the framework once and reuses that booted application instance across
jobs — it doesn't re-read `.env` per job. The worker had been started
before the relevant `.env` values were in place, so it was running
against a stale in-memory config snapshot. Restarting the specific
worker process (identified by its full command line via
`Get-CimInstance Win32_Process`, not a blanket `taskkill /IM php.exe`)
picked up the current environment.

**Investigation, part two — the actual provider error.** With the
provider switch confirmed correct, the next attempt failed with a real
`404` from Google's API. A `404` on a REST endpoint usually means "bad
URL," which would point at a bug in this milestone's own request
construction — but the request shape had already been checked against
Google's live API reference documentation (fetched the same session)
and matched exactly: `POST /v1beta/models/{model}:generateContent`,
`x-goog-api-key` header. Rather than assume the credential was
malformed (a real possibility, given its unusual `AQ.`-prefixed
format), the response body was read directly — Google's own error
message named the actual cause: *"This model models/gemini-2.5-flash
is no longer available to new users."* A model-availability error, not
a routing or auth error, surfaced as a 404 specifically because
Google's API returns 404 rather than 403 for some access-denied cases
on named resources, to avoid confirming a resource's existence to a
caller who can't access it.

**Confirming the credential itself was fine.** Probed four candidate
model IDs directly against the same key, via `php artisan tinker`,
printing only the resulting HTTP status codes — never the key itself.
`gemini-2.0-flash` and `gemini-2.5-pro` both returned `429` (quota/rate
limited), which only happens *after* authentication succeeds; a bad
key would have produced `401`/`403` on every model, not a 404 on some
and a 429 on others. This distinguished, conclusively, "the key is
wrong" from "this specific model isn't available to this key" without
ever needing to inspect or share the credential's actual value.

**Decision.** Switched the default Gemini model to `gemini-2.0-flash`.
Attempting a full live success-path demonstration afterward hit a
third, distinct failure: `429`, `"quota exceeded for metric:
generate_content_free_tier_input_token_count"` — a daily free-tier
quota, confirmed by the specific metric name in Google's response, not
a short-lived rate limit that would clear within the session. This was
accepted as the natural stopping point for live verification rather
than continuing to consume a limited, non-resetting resource — the
error path this produced (retry with backoff, then a clean failed
state) was itself the thing verified live instead.

**Lessons learned.** Three genuinely different failure classes —
stale in-process config, a real external API's deprecated-resource
error, and an exhausted usage quota — surfaced through similar-looking
symptoms (a generic-looking error at first, then a 404, then a 429).
Each needed a different, targeted investigation rather than a single
guess-and-retry loop: checking fresh process state directly, reading
the external system's own error message instead of inferring one from
a status code alone, and using a *second* real request's response
(the 429s) as positive evidence about the credential rather than
treating the first failure as conclusive. Documented here so a future
session touching this integration doesn't have to re-derive any of the
three.

---

## 2026-07-16 — GraphQL enums serialize as their schema name, not their internal directive value

**Problem.** `RecentActivity` — unmodified since Milestone 5 — crashed
in a live browser check with a React "element type is invalid"
error, immediately after migrating its data source from a REST
endpoint to the new GraphQL `dashboardOverview` query.
`typecheck`, `lint`, and `npm run build` had all passed cleanly
moments earlier.

**Investigation.** The schema defines `ActivityItem.type` as an enum:

```graphql
enum ActivityType {
    POST_PUBLISHED @enum(value: "post-published")
    DRAFT_CREATED @enum(value: "draft-created")
    SITE_CONNECTED @enum(value: "site-connected")
}
```

The resolver (`DashboardOverview::__invoke()`) returns
`ActivityItemData` DTOs whose `type` property already holds the
internal value (`"post-published"`, unchanged since Milestone 5).
The assumption going in was that the GraphQL response would therefore
also contain `"post-published"` — the `@enum(value: ...)` directive's
whole apparent purpose is mapping between an internal value and a
schema name, so it seemed reasonable that the internal value would be
what a client actually receives. It is not: per the GraphQL
specification, enum values serialize over the wire using their
**schema-defined name** (`"POST_PUBLISHED"`) in every case — the
`@enum` directive's actual job is translating between the two
directions (accepting `POST_PUBLISHED` as an *input* argument and
mapping it to `"post-published"` for a resolver to consume, and
mapping a resolver's returned `"post-published"` to `POST_PUBLISHED`
for the *output*). The frontend's `ACTIVITY_ICONS` lookup
(`Record<ActivityType, LucideIcon>`, keyed on the lowercase-hyphenated
internal values since Milestone 5) received `"POST_PUBLISHED"` and
looked up a key that didn't exist, yielding `undefined` — rendered
directly as a JSX component, which is exactly the crash React reported.

**Decision.** Added an explicit wire-format translation at the single
point GraphQL data enters the frontend (`useDashboardOverview`'s
`queryFn`, before `select` or any component ever sees the data) —
mapping `"POST_PUBLISHED"` → `"post-published"` etc. — rather than
updating every downstream consumer to key on the new wire format.

**Outcome.** `RecentActivity` renders correctly with zero changes to
the component itself. Verified live: a real browser session shows
correct icons for real seeded activity items, zero console errors.

**Lessons learned.** Passing `typecheck`/`lint`/`build` verifies
internal consistency against the types the code declares — it cannot
catch a wrong assumption about what an external system's wire format
actually is, because TypeScript trusts the type annotation, not the
runtime payload. This is exactly the category of defect only a real
integration check (an actual browser hitting the actual API) can
catch, and is a specific, non-obvious trap for anyone using Lighthouse
(or likely other schema-first GraphQL frameworks with a similar
internal-value-mapping directive) enum types on an *output* field for
the first time — the natural mental model of "the directive maps
between the two representations" doesn't make clear which direction
applies to which side of the wire without reading the spec/
implementation directly.

---

## 2026-07-16 — A stale framework cache silently blocked a new package's service provider

**Problem.** Immediately after `composer require nuwave/lighthouse`
completed successfully, the package didn't appear anywhere in
`php artisan package:discover`'s output, `php artisan vendor:publish
--tag=lighthouse-config` reported "No publishable resources," and
`php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider"`
reported the same — as if the package weren't installed at all,
despite `composer show nuwave/lighthouse` confirming it was.

**Investigation.** Checked `vendor/nuwave/lighthouse/composer.json`'s
`extra.laravel.providers` list directly — the provider *was* correctly
declared for auto-discovery, and `composer.json`'s own
`extra.laravel.dont-discover` (which can opt packages out) was empty.
Recognized the actual shape of the problem immediately from a
previously-documented incident: `bootstrap/cache/services.php` — the
compiled, cached list of every registered service provider — had a
modification timestamp from two days before this package was
installed. This is the same OneDrive-synced-path cache-staleness
class of issue first documented in this project's Milestone 6
Engineering Journal entry, recurring here for a completely different
package.

**Decision.** Deleted `bootstrap/cache/services.php` (and the
adjacent stale `packages.php`) and re-ran `php artisan package:discover`.

**Outcome.** Lighthouse's provider appeared immediately;
`vendor:publish` worked on the very next attempt.

**Lessons learned.** The value of writing down a root cause the first
time it's found is fully realized the second time it happens on
unrelated work — this was recognized and fixed in under a minute
specifically because it matched an already-documented pattern, instead
of costing a fresh multi-step investigation (checking composer.json,
checking discovery output, checking dont-discover lists) all over
again. Worth checking `bootstrap/cache/services.php`'s timestamp
first, before anything else, the next time a freshly-installed
Laravel package doesn't appear to be registered at all in this
project's environment.

---

## 2026-07-15 — A DB-level unique index doesn't know about `SoftDeletes`

**Problem.** A test for "replacing a post's WordPress featured image
on re-sync" failed with a real `QueryException` — inserting the new
`Media` attachment row after soft-deleting the old one for the same
post violated a unique constraint on
`(mediable_type, mediable_id, collection)`.

**Investigation.** The constraint was added deliberately, during
implementation, as the obvious way to enforce "one featured image per
post" at the database layer. `SoftDeletes` sets `deleted_at` but never
removes the row — a unique index has no awareness of that column, so
the "old" (soft-deleted) row still physically occupies the constraint
for `(Post, 'featured_image')`, and the new row's insert collides with
it. Checked whether this was specific to the new `media` table:
`posts`' own `(site_id, wordpress_post_id)` unique index (Milestone
10) coexists with `Post`'s own `SoftDeletes` and carries the identical
tradeoff — apparently never exercised, since no existing workflow
re-creates a soft-deleted post with the same WordPress ID.

**Decision.** Removed the new unique constraints (on the polymorphic
attachment slot, and on `(site_id, source_id)`) and kept them as plain
indexes. The actual invariants — one attachment per slot, no duplicate
downloads of the same external resource — are enforced in
`WordPressPostMapper::syncFeaturedImage()` and `DownloadMediaJob`
instead, which is where the business logic already lived and which
already reasons about "current" vs. "replaced" attachments correctly.

**Outcome.** All three affected tests (replace, remove, no-duplicate-
download) pass. The equivalent, previously-unnoticed risk on `posts`
is now a named item in `docs/ENGINEERING_JOURNAL.md`'s Future Backlog
rather than a silent one.

**Lessons learned.** A unique constraint that looks obviously correct
in isolation can still be wrong once a cross-cutting concern
(`SoftDeletes`) is layered on top — the two features are each
individually standard, well-understood Laravel patterns, but their
combination has a real gap neither one's documentation calls out on
its own. Worth explicitly checking "does this table also use
SoftDeletes" before adding a unique constraint to a new column
combination, and worth checking whether an *existing* table already
has the same combination unexercised, once the interaction is
understood once.

---

## 2026-07-15 — `Route::apiResource()` silently mis-binds when English pluralization surprises you

**Problem.** Every request to `GET`/`PATCH`/`DELETE /api/v1/media/{id}`
failed authorization with "call to a member function `hasMember()` on
null" — thrown from inside `MediaPolicy`, on `$media->workspace`. No
exception was thrown resolving `$media` itself, and no 404 occurred;
the policy method received a `Media` instance that looked entirely
normal until its relation was accessed.

**Investigation.** Dumped the resolved model's raw attributes directly
inside the policy method rather than guessing further — `getAttributes()`
returned an empty array. The model wasn't the row at all; it was a
freshly-constructed, never-hydrated `new Media()`. `Route::apiResource('media',
MediaController::class)` singularizes the resource name to generate
its URI parameter — and English "media" is already the plural of
"medium," so Laravel generated `{medium}`, not `{media}` (confirmed
via `php artisan route:list --path=media`). The controller's methods
type-hint `Media $media` (matching the *model*, correctly), so
Laravel's implicit route-model-binding — which matches by the
controller parameter's *variable name* against the route's captured
parameter name — found no `medium` variable to bind, silently declined
to substitute anything, and the container fell back to constructing a
blank instance for the type-hinted class instead of raising any error.

**Decision.** Added `->parameters(['media' => 'media'])` to the
resource route registration, forcing the URI parameter to `{media}`
regardless of the resource name's grammatically-correct singular form.

**Outcome.** `route:list` confirms `{media}`; every `MediaController`
action resolves the real row and its relations correctly. Verified
directly (not just re-running the failing test) by dumping the route
table before and after.

**Lessons learned.** "Route model binding failed" doesn't always
surface as a 404 or a thrown exception — a variable-name mismatch
between a convention-generated route parameter and a hand-written
controller signature can produce a fully-constructed, silently-empty
object instead, which then fails much later and further from the real
cause (a null-relation error deep inside authorization logic, not a
routing error). "Media" is a specific, known English-inflector trap
(it's already plural — "medium" is the singular) worth remembering
the next time a resource name ends in a word that might already be a
plural form; `php artisan route:list` is the fastest way to confirm
what a resource route's parameter actually got named, rather than
assuming it matches the resource name.

---

## 2026-07-15 — A Playwright click on a Next.js `<Link>` silently didn't navigate (recurrence)

**Problem.** A verification script clicked a post title link, waited,
then asserted on the resulting page — and got the *previous* page's
content back (a stale `<h1>`), with the URL confirmed unchanged after
the click.

**Investigation.** This is the same interaction quirk
`docs/SESSION_HANDOFF.md` already documented during Milestone 11's own
verification: Next.js App Router client-side navigation and
Playwright's default `locator.click()` + `waitForURL()` combination
don't reliably observe each other the way a full page navigation
does. Confirmed directly by checking `page.url()` immediately after
the click — it hadn't changed at all, ruling out a timing race and
confirming the click genuinely never triggered navigation as far as
the test could observe.

**Decision.** Replaced the click-then-wait pattern with a direct
`page.goto()` to the target URL for this verification step, matching
the workaround already recorded from Milestone 11.

**Outcome.** The page loaded correctly on the first attempt with the
real content.

**Lessons learned.** This is now the *second* time this exact
interaction has cost investigation time despite being previously
documented — worth escalating from "a note in a session-snapshot
file" to this permanent journal entry, and worth checking first (not
last) the next time a Playwright click-then-navigate step in this
project produces stale content instead of assuming a new app defect.
A `SESSION_HANDOFF.md` entry gets overwritten every milestone; a real,
recurring gotcha belongs somewhere durable.

---

## 2026-07-14 — The `sync` queue driver rethrows job failures instead of swallowing them

**Problem.** Writing the first failure-path test for `SyncWordPressPostsJob`
raised a design question before any code was wrong: with
`QUEUE_CONNECTION=sync` (this project's own test-environment default,
`phpunit.xml`), does dispatching a job that ultimately fails behave
like a real queue worker (retry, then quietly call `failed()`), or
does the exception surface back to whatever called `::dispatch()`?
Getting this wrong would mean either a test asserting the wrong HTTP
status, or a production code path silently depending on test-only
behavior.

**Investigation.** Traced Laravel's `Illuminate\Queue\SyncQueue::push()`
directly rather than guessing from the `database`/`redis` drivers'
behavior. `SyncQueue` doesn't go through `Illuminate\Queue\Worker` at
all — no retry loop, no backoff sleep, no attempt counter. It executes
the job's `handle()` once, and on a thrown exception, calls
`FailingJob::handle()` (which invokes the job's own `failed()`
callback) and then **re-throws the original exception** back up
through the `::dispatch()` call site.

**Consequence, used deliberately rather than fought.** In this
project's tests, `SyncWordPressPostsJob::dispatch($site)` inside
`ContentSyncController::sync()` — when the job ultimately fails —
throws synchronously, uncaught by the controller, and renders through
the existing `ApiExceptionHandler` exactly as it did before this
milestone's synchronous-to-async refactor. This meant the pre-existing
test `marks the site as errored when WordPress is unreachable during
sync` (originally written for the fully-synchronous M10 flow) needed
**zero changes** to keep passing — a genuine, verified case of the
test environment's queue driver preserving an existing contract by
coincidence of its own implementation, not because I'd deliberately
designed around it.

**Lessons learned.** "Runs the job" and "behaves like a queue" are not
the same guarantee — the `sync` driver is genuinely useful for tests
specifically because it runs jobs inline, but its failure semantics
(no retry, immediate rethrow) are a real behavioral difference from
every other driver, worth confirming explicitly rather than assuming
just because a test happened to pass. Documented here so a future
milestone adding queue tests doesn't have to re-derive this from
scratch.

---

## 2026-07-14 — `Http::fake()` called twice mid-test doesn't override the first call

**Problem.** A test asserting that re-syncing changed WordPress content
produces an `updated` result (not `created`/`skipped`) failed with the
*first* sync's fixture data still active during the *second* sync call
— `skipped: 2` instead of the expected `updated: 1`, even though the
test called `fakeWordPressPostsCollection()` then, after the first
sync, a second helper returning different fixture data before the
second sync.

**Investigation.** Laravel's `Http::fake()` accepts an array of URL-
pattern → response rules and can be called multiple times per test —
documented as additive, but the actual matching behavior when two
calls register a rule for the *same* pattern is first-registered-wins,
not last-registered-wins. The second call's rule for
`*/wp-json/wp/v2/posts*` was silently never consulted; the first
call's rule kept resolving every subsequent request matching that
pattern for the rest of the test.

**Decision.** Replaced the two separate `Http::fake()` calls with one
call using `Http::sequence()->push(...)->push(...)` for the single URL
pattern under test — sequences are explicitly ordered and exist for
exactly this "different response on each successive call to the same
endpoint" case, unlike two independent `Http::fake()` calls.

**Outcome.** The update-detection test passes deterministically; the
now-unused single-response fixture helper was deleted rather than left
as dead code once the sequence-based version replaced its only call
site.

**Lessons learned.** "Additive" and "last call wins" are not the same
guarantee — when a mocking API documents that repeated setup calls
merge rather than replace, check specifically what happens on a
pattern collision before assuming later calls in test order take
precedence, especially for any test that intentionally changes a mock's
behavior partway through (before/after assertions around a state
change are exactly the shape most likely to trigger this).

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