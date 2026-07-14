# Milestone 10.1 Report

## Date

2026-07-14

---

## Objective

Complete the transition from a demo application into a fully
API-driven SaaS platform, before introducing queues, Docker, or CI/CD
infrastructure. Eliminate remaining frontend mock dependencies where
practical, and complete the API surface so the frontend communicates
exclusively with Laravel through production-shaped endpoints. Both a
feature milestone and a technical-debt-reduction milestone.

This is this ROADMAP slot's original scope ("API Completion & Frontend
Migration"), displaced when Milestone 10 was redefined to Content
Synchronization Platform by explicit brief, and completed here as
Milestone 10.1.

---

## Executive Summary

Milestone 10.1 is complete and, on independent review, sound. The
repository review surfaced the milestone's real central question
before any code was written: "eliminate mock dependencies" is not one
mechanical operation — it's six independent audits, each with a
potentially different correct answer. That distinction held up under
implementation. Four of the six remaining mocked dashboard widgets
(Recent Activity, Analytics Preview, Recent Drafts, System Health)
became real backend-driven endpoints. Quick Actions became honestly
half-real — two of its four actions now navigate to destinations that
already exist, the other two stay genuinely disabled because nothing
real exists for them to point at. AI Assistant Preview was reviewed
and deliberately left mocked, unchanged since its Milestone 5/7
deferral, because there is still no real AI provider integration
(Milestone 14's job) — not an oversight, but a repeated, consistent
application of this project's own established discipline for
distinguishing "not yet migrated" from "deliberately deferred."

Every new endpoint was built by extending existing architecture, not
introducing a parallel one: `IndexPostsRequest` gained one new accepted
`status` value reusing the already-existing `Post::scopeUnpublished()`
scope, rather than a second endpoint; a duplicated database health
check was extracted into a shared `DatabaseHealthChecker` rather than
copied; Recent Activity was built as a live derivation from existing
`Post`/`Site` timestamp columns rather than a new event-log table, the
same "don't add a table before real usage justifies it" discipline
`docs/adr/0005-domain-model.md` already established for the "AI Jobs"
table.

The technical-debt sweep closed a Future Backlog item flagged across
three prior milestone reviews ("Six of nine dashboard widgets remain
on the mock service layer," open since Milestone 5) and reviewed —
rather than silently reflexively implemented — the pagination gap
named since Milestone 7, deferring it again with updated reasoning
rather than either ignoring it or scope-creeping into an unrelated
UI feature.

Backend test coverage grew from 83 to 95 tests, zero regressions. A
live `axe-core` accessibility pass against both pages carrying
entirely new real-data content returned zero violations on each.

---

## Architecture Review

Read in full ahead of implementation: `docs/AI_ENGINEERING_CONTEXT.md`,
`docs/SESSION_HANDOFF.md`, `docs/PROJECT.md`, `docs/ROADMAP.md`,
`docs/CODING_STANDARDS.md`, `docs/DEVLOG.md`,
`docs/ENGINEERING_JOURNAL.md` (including the full Future Backlog),
`docs/prompts/milestone-lifecycle.md`, every ADR (0001–0008), and the
M8/M9/M10 milestone reports.

**Domain-by-domain audit, the actual first deliverable of this
milestone:**

| Domain | Real data available? | Decision |
| --- | --- | --- |
| Recent Activity | Yes, via existing `Post`/`Site` columns | Built as a live derivation, no new table |
| Analytics Preview | Yes, `AnalyticsSnapshot` (Milestone 7) | Aggregated per day across the workspace |
| Recent Drafts | Yes, via `Post::scopeUnpublished()` | Reused the existing scope + endpoint |
| System Health | Partially — DB check and `Site` status are real; a queue is not | Real where possible, one honest placeholder |
| Quick Actions | Two of four actions have real destinations | Two real links, two stay disabled |
| AI Assistant Preview | No — no provider integration exists | Left deliberately mocked (Milestone 14) |
| Settings | Real workspace/user data exists; no editable-preferences design exists | Real, read-only |
| Notifications | No backend domain exists at all | Reviewed, deliberately not invented |
| Content Sync | Already real (Milestone 10) | No change needed |
| Pagination (Sites/Posts) | N/A — a UI/page-size decision, not a data question | Reviewed, deliberately deferred again |

Every "extend the existing architecture" rule from the brief was
checked against actual code, not assumed: `CurrentWorkspaceResolver`,
`SitePolicy`/`PostPolicy`, `ApiResponse`/`ApiExceptionHandler`, Form
Requests, the Service layer, and DTOs are used unchanged by every new
endpoint — no new authentication, authorization, or response-envelope
mechanism was introduced.

---

## Implementation Summary

**Backend — four new real domains, one refactor.**
- `App\Support\DatabaseHealthChecker` — extracted from
  `HealthController`'s private `checkDatabase()` method, now shared by
  `HealthController` and the new `SystemHealthService`, closing a real
  duplication finding.
- `AnalyticsService::visitorsByRange()` + `IndexAnalyticsRequest` +
  `AnalyticsPointData`/`AnalyticsPointResource` — real per-day visitor
  aggregation from `AnalyticsSnapshot`, zero-filled across the
  requested range (`7d`/`30d`/`90d`).
- `SystemHealthService::status()` + `SystemHealthData`/`SystemHealthResource` —
  real `apiStatus` (via `DatabaseHealthChecker`), real
  `wordpressConnection` (derived from workspace `Site.status` values),
  real `storageUsedPercent` (aggregated `Site` storage columns), and an
  honest hardcoded `backgroundQueue` placeholder.
- `SettingsService::forUser()` + `SettingsData`/`SettingsResource` —
  real workspace name/slug/member count and user name/email/role.
- `DashboardService::recentActivity()` + `ActivityItemData`/`ActivityItemResource` —
  three queries (published posts, draft posts, connected sites) merged
  and sorted by recency in application code, no new table.
- `PostResource` gained `site_name` (eager-loaded via `with('site:id,name')`
  in every controller action that returns it, no new N+1).
- `IndexPostsRequest` gained one new accepted `status` value,
  `unpublished`, mapping to the existing `Post::scopeUnpublished()`
  scope inside `PostController::index()`.
- New routes: `GET /dashboard/activity`, `GET /analytics`,
  `GET /system-health` (all inside the existing `auth:sanctum` +
  `ResolveCurrentWorkspace` group).

**Frontend — four hooks migrated, one component rewired, one new
feature folder, one directory deleted.**
- `src/services/api/{analytics,system-health,settings}.service.ts`
  (new) and extensions to `dashboard.service.ts`/`posts.service.ts`.
- Five new mapper utilities (`map-activity`, `map-analytics-points`,
  `map-posts-to-drafts`, `map-system-health`, `map-settings`) —
  every widget component itself required zero changes beyond
  `recent-activity.tsx`'s icon map (a direct consequence of
  `ActivityType`'s union changing shape, not a design change).
- `use-recent-activity.ts`, `use-analytics-preview.ts`,
  `use-recent-drafts.ts`, `use-system-health.ts` — same hook
  signatures, real data sources. `use-recent-drafts.ts` lost its
  `retry: 1` override — that existed solely to demonstrate the mock's
  contrived deterministic failure, which no longer exists.
- `quick-actions.tsx` — rewritten with per-action `href` (two real
  `Link`s, two disabled `button`s), config moved from the deleted
  `services/mock/` into the component itself.
- New `src/features/settings/` (types, utils, hooks, components) — a
  real `SettingsSummary` component, `settings/page.tsx` updated.
- `src/services/mock/` deleted in full — nothing imports from it
  anymore.

---

## Validation Results

- `php artisan test`: **95/95 passing** (up from 83) — 12 new tests in
  `tests/Feature/ApiCompletionTest.php` covering every new endpoint,
  workspace scoping, the `unpublished` filter, and `site_name`.
  Zero regressions across the full existing suite.
- `./vendor/bin/pint --test` on every new/modified backend file: pass.
- `npm run typecheck`: pass.
- `npm run lint`: pass.
- `npm run build`: pass — 11 routes, `/settings` grew from a static
  812 B placeholder to a 3.61 kB real client-data page.
- Live browser verification (production build): logged in, loaded
  `/dashboard`, confirmed every one of the eight widgets renders real
  content with no `undefined`/`NaN` leakage and zero console errors;
  clicked both real Quick Actions links and confirmed they navigate to
  `/wordpress` and `/analytics`; loaded `/settings` and confirmed the
  seeded workspace's real name/slug/member count and the logged-in
  user's real name/email/role render correctly.
- `axe-core` audit against `/dashboard` and `/settings` (the two pages
  carrying entirely new real-data content this milestone): **zero
  violations on both.**

---

## Technical Debt Resolved

- **"Six of nine dashboard widgets remain on the mock service layer"**
  (open since Milestone 5, referenced in the Milestone 6, 7, and 9
  reviews) — resolved. Every widget is now either real or a
  deliberately, explicitly documented placeholder.
- **"Recent Drafts' deterministic failure is module state, not request
  state"** (Milestone 5) — moot and resolved; the contrived mock
  failure this item described no longer exists.
- **Duplicated database health-check logic** — found and closed during
  this milestone's own implementation (not a pre-existing Future
  Backlog item): `HealthController` and `SystemHealthController` now
  share one `DatabaseHealthChecker` instead of two copies of the same
  try/catch.
- **Dead code removed:** `src/services/mock/` (both files) deleted in
  full once every consumer was migrated — no dead exports, no
  commented-out fallback paths left behind.

---

## Deferred Work

- **Pagination on `sites`/`posts` index endpoints** (named since
  Milestone 7) — reviewed again as part of this milestone's own
  technical-debt sweep, deliberately deferred a second time. Still
  needs a real page-size/UI decision; not this milestone's actual
  objective (mock-to-real migration).
- **AI Assistant Preview / the AI domain** — unchanged, deliberately.
  No real AI provider integration exists; Milestone 14's job.
- **Notifications as a real backend domain** — reviewed, deliberately
  not built. No product decision exists yet about what constitutes a
  notification or how read/unread state should persist; inventing one
  now would be unscoped guessing, the same category this project
  already avoids for Registration and "AI Jobs."
- **Editable Settings** — real read data exists now; no form, no
  `PATCH` endpoint, no decided answer to "what can a user change."
- **System Health's real background-queue metrics** — the placeholder
  (`0` pending, `operational`) becomes real the moment Milestone 11
  ships an actual queue to report on.
- **New Post / Generate AI Draft quick actions** — stay disabled; no
  post-creation UI and no AI backend exist yet to point them at.

---

## Risks

- **`DashboardService::recentActivity()`'s three-query merge** is a
  real, accepted performance trade-off, not a correctness risk — each
  query is independently indexed and workspace-scoped; the only cost
  is three round-trips instead of one, acceptable at today's real
  per-workspace data volume.
- **No new security surface.** Every new endpoint reuses
  `auth:sanctum` + `ResolveCurrentWorkspace` + the existing
  `ApiExceptionHandler`; none introduce a new authorization mechanism,
  new client-writable field, or new external call. `Settings`
  specifically returns only data the requesting user is already
  entitled to see (their own profile, their current workspace).
- **`PostResource`'s new `site_name` field** required eager-loading
  `site:id,name` at every call site returning `PostResource` — verified
  directly (all four `PostController` actions checked, confirmed no
  N+1 introduced) rather than assumed safe.

---

## Trade-offs

- **System Health's `backgroundQueue` is not real** — a deliberate,
  named placeholder rather than fabricating a plausible-looking metric
  for infrastructure that doesn't exist yet. The alternative (hiding
  the field entirely until Milestone 11) would mean a visible UI
  regression for no real benefit; showing an honest `0 pending` is
  more useful and no less honest than omitting it.
- **No pagination this milestone** — a real, cheap-to-reverse scoping
  decision. Building it now, without a proven current data-volume
  need and without a decided page-size/UI shape, risks exactly the
  premature-abstraction cost this project's own standing rules warn
  against.
- **Recent Activity has no persisted event log** — chosen over a new
  table specifically because the source data (`Post`/`Site` timestamps)
  is already authoritative and already exists; a dedicated table would
  duplicate it for a read-heavy, write-rare feed.

---

## Production Readiness

The API surface this milestone completes is genuinely production-
shaped: every new endpoint goes through the same envelope, the same
authorization pipeline, and the same tenant isolation every prior
milestone established, verified by dedicated tests rather than
asserted. The frontend no longer fabricates any business data — every
number, chart, and list on the dashboard and settings pages traces to
a real, tested backend query. The two places real functionality
remains incomplete (background-queue metrics, editable settings) are
named, bounded, and intentionally sequenced behind real product/
infrastructure decisions rather than silently missing.

---

## Recommendation for the Next Milestone

Both Milestone 10.1 (this one) and Milestone 10 (Content
Synchronization) are now complete. Two real candidates for what comes
next per `docs/ROADMAP.md`:

- **Milestone 11 (Background Jobs & Queues)** — the more natural
  continuation: it's the direct seam both `ContentSyncService::sync()`
  (Milestone 10) and System Health's `backgroundQueue` placeholder
  (this milestone) were explicitly built to attach to, and closes two
  named "not yet real" items at once.
- **Milestone 12 (Storage & Media)** — a reasonable alternative if
  content management depth is prioritized over infrastructure next.

Recommend Milestone 11, since it retires the most currently-open named
placeholders (synchronous content sync, the background-queue metric)
with a single piece of real infrastructure, rather than opening a new
domain while those two stay outstanding. Waiting for explicit approval
before starting either, per this milestone's own stop condition.
