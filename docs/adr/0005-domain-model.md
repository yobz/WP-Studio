# 0005 — Domain Model

**Status:** Accepted (Milestone 7)

## Decision

Establish `Workspace` as the tenant boundary every other domain
concept hangs off; give `Site` and `Post` real CRUD APIs backed by
Form Requests, API Resources, and thin Services; replace Milestone 6's
denormalized `sites.monthly_visitors` column with a real
`AnalyticsSnapshot` history table (enabling an actual period-over-
period trend, not just a point-in-time number); add `PublishingJob` as
a placeholder for future async publishing; and write real
authorization logic in `SitePolicy`/`PostPolicy` against the new
Workspace/User membership model — without wiring any of it into a
route yet, since there's still no authenticated request to check it
against.

## Context

**What Milestone 6 left unresolved.** The backend foundation shipped
exactly two tables (`sites`, `posts`) with no tenant concept — every
site existed in a flat, ownerless list. That was correct scope for a
"prove the architecture works" milestone, but it can't be the
permanent shape: a real SaaS product needs to know *whose* sites these
are before Milestone 8 (Authentication) can mean anything. This
milestone's job is answering that question — establishing the
business domain, not just adding more tables.

**Why domain modeling came before migrations.** The brief was explicit
about this order, and it mattered in practice: reasoning through
ownership (`Workspace` → `Site` → `Post`/`AnalyticsSnapshot`;
`Workspace` ↔ `User` many-to-many) before writing a single `Schema::create`
call is what surfaced the pivot-table naming issue (see the
Engineering Journal) and the pending indexes below *before* they
shipped, not after.

## Domain Model

**Entities and what they own:**

| Entity | Owns / relates to | Lifecycle |
| --- | --- | --- |
| `Workspace` | many `Site`; many `User` (via `workspace_user`, with a `role`) | Tenant root. No delete flow built yet (deliberately — see Trade-offs); hard-deletes cascade to its sites today. |
| `Site` | many `Post`; many `AnalyticsSnapshot`; belongs to one `Workspace` | Created via `SiteController::store()` → `SiteService::create()` (dispatches `SiteConnected`). Soft-deletable — see below. |
| `Post` | many `PublishingJob`; belongs to one `Site` | Created via `PostController::store()` → `PostService::create()`. Soft-deletable. |
| `AnalyticsSnapshot` | belongs to one `Site` | One row per site per day, immutable once written (nothing updates a snapshot — a future job would create the *next* day's row, not edit an old one). Never deleted by application code. |
| `PublishingJob` | belongs to one `Post` | Created via `PublishingService::schedule()` (status `pending`). Nothing transitions it past `pending` yet — no queue worker exists. |
| `User` (placeholder integration) | many `Workspace` (via `workspace_user`) | Unchanged model, already existed for future Sanctum auth (Milestone 6). This milestone is the first time it's *used* — as the workspace-membership party. |

**"Content," "Publishing," and "AI Jobs" are domain areas, not
separate tables.** "Content" is `Post`; "Publishing" is
`PublishingJob`; there's no reason for a `Content` table distinct from
`Post` — it would be the same rows with a different name. **AI Jobs is
documented here but deliberately has no table yet** — unlike
`PublishingJob`, whose shape (status, attempted/completed timestamps,
error message) is a well-understood, generic "async operation record"
pattern, an AI job's real shape (which provider, which model, prompt,
token/cost accounting, streaming vs. polling) isn't knowable yet
without designing against a real provider integration. Guessing that
schema now would very likely mean a breaking migration once the real
AI milestone starts. Deferred, not built — see Future Backlog.

## Database

**Migrations** (`backend/database/migrations/`), in dependency order:
`workspaces` → `workspace_user` → `sites` (amended, not a new
migration — see below) → `posts` (amended) → `analytics_snapshots` →
`publishing_jobs`.

**Amending Milestone 6's `sites`/`posts` migrations directly, not
layering `ALTER TABLE` migrations on top.** Both migrations are still
unreleased — nothing outside this local development environment has
ever run them. Editing them in place (adding `workspace_id`,
`softDeletes()`, indexes; removing `monthly_visitors` from `sites`
entirely) produces a schema history that reads as "this is what the
table always looked like," which is more honest than a trail of
`add_workspace_id_to_sites_table` migrations for a table that was
never actually shipped without it. This would be the wrong call for a
migration that had run in a shared/deployed environment — editing
history that other people or environments depend on is genuinely
dangerous — but doesn't apply here yet.

**Foreign keys.** Every relationship is a real `foreignId()->constrained()`,
not an unconstrained integer column — `sites.workspace_id`,
`posts.site_id`, `analytics_snapshots.site_id`,
`publishing_jobs.post_id`, `workspace_user.workspace_id`/`user_id`.
Cascade-on-delete throughout (deleting a workspace removes its sites;
deleting a site removes its posts and snapshots; deleting a post
removes its publishing jobs) — the alternative, `restrict`, would mean
a workspace could never be deleted while it had sites, which is a real
product decision (soft-delete the workspace instead?) this milestone
isn't making yet. Documented as a deferred decision, not an oversight.

**Indexes — including two gaps this milestone's own self-review
caught.** `sites`: `workspace_id`, `status`. `posts`: `(site_id,
status)` composite, `published_at`. `analytics_snapshots`: unique
`(site_id, snapshot_date)`. `publishing_jobs`: `(post_id, status)`.
`workspace_user`: unique `(workspace_id, user_id)`, plus a standalone
`user_id` index. `workspaces`: unique `slug`.

The `workspace_id` index on `sites` and the standalone `user_id` index
on `workspace_user` were **not** in the first draft of these
migrations — self-review caught both. The reasoning is SQLite-specific
and worth stating plainly: **MySQL/InnoDB automatically indexes a
column the moment a foreign key constraint references it; SQLite does
not.** `foreignId()->constrained()` adds the *constraint* on both
drivers, but only guarantees an index on MySQL. Since this project
runs SQLite locally (see
[[0004-backend-foundation]](0004-backend-foundation.md)), relying on
the constraint alone would mean `WHERE workspace_id = ?` and
`$user->workspaces` (which filters `workspace_user` by `user_id`
alone — the *non-leftmost* column of its unique composite index, so
the composite doesn't cover it) both silently full-table-scan in local
development, with no error to reveal it — the query still works,
it's just slow, and slow is invisible at today's seed-data scale.
Explicit indexes added for both.

**Soft deletes — `Site` and `Post` only, not `Workspace`,
`AnalyticsSnapshot`, or `PublishingJob`.** The two content-bearing
entities an operator plausibly deletes by mistake (or wants to
temporarily disconnect without losing history) get `SoftDeletes`.
Deliberately **not** applied to:
- `Workspace` — deleting an entire tenant is a bigger, more deliberate
  operation than a quick undo-able delete; it deserves its own
  future flow (data export, grace period, explicit confirmation), not
  a bolted-on `deleted_at` column that implies "just restore it" is
  sufficient.
- `AnalyticsSnapshot` — immutable historical record; there's no
  "accidentally deleted a snapshot" scenario worth designing for, and
  soft-deleting analytics data adds query complexity (every aggregate
  query needs to remember to exclude trashed rows) for no real
  benefit.
- `PublishingJob` — operational/ephemeral records, not content; a
  failed or completed job is more naturally handled by a future
  retention policy than a recoverable delete.

**UUIDs — deferred, not implemented.** Every table uses auto-increment
integer primary keys. UUIDs matter once IDs are exposed to external
clients who shouldn't be able to enumerate them (`/sites/1`,
`/sites/2`, ...) or once multi-region/offline-first writes need
collision-free IDs generated client-side. Neither applies yet — there's
no external API consumer besides this project's own frontend, and
there's exactly one database. Revisit before any public API or
multi-region deployment; switching later means adding a `uuid` column
and a lookup path, not necessarily replacing the primary key.

## Eloquent Models

**Relationships, scopes, casts** — see `backend/app/Models/`.
Highlights: `Workspace::roleFor(User $user): ?WorkspaceRole` and
`hasMember(User $user): bool` are the two methods every policy check
goes through — one place that knows how to answer "is this user in
this workspace, and with what role," rather than every policy method
re-deriving it. `Site::scopeConnected()`, `Post::scopePublished()`/
`scopeUnpublished()` — extracted because `DashboardService` needed the
exact same filters Milestone 6 had inlined as raw `where()` calls;
scopes make the filter reusable and independently testable (see
`DomainRelationshipsTest`).

**Factories and seeders.** `WorkspaceFactory`, `AnalyticsSnapshotFactory`,
`PublishingJobFactory` added; `SiteFactory` gained `workspace_id`
(via `Workspace::factory()`) and lost `monthly_visitors`.
Milestone 6's `SiteSeeder` is replaced by `DemoDataSeeder` — the old
name stopped describing what it seeds (a whole workspace graph: a
user, a workspace, sites, posts, *and* 28 days of trending
`AnalyticsSnapshot` history per site, specifically so
`DashboardService`'s new trend calculation has real current/previous
periods to compare — see below).

## Service Layer

`SiteService`, `PostService` — thin: validate (Form Request) →
service call → Resource. The one piece of real logic:
`SiteService::create()` dispatches `SiteConnected` (defined but never
dispatched in Milestone 6) — this is that event's first real use.

`PublishingService::schedule()` — placeholder, records intent (creates
a `PublishingJob` row) without processing anything. The method
boundary exists now specifically so a future `ProcessPublishingJob`
queued job can be dispatched from inside it later, making that
addition additive rather than a refactor of calling code.

`DashboardService` — the one service this milestone materially
changed. Milestone 6's `monthlyVisitors: sum(sites.monthly_visitors)`
becomes a real period-over-period query against
`AnalyticsSnapshot`: sum of `visitors` across connected sites for the
trailing 14 days ("current"), compared against the 14 days before
that ("previous"), expressed as a percentage change
(`monthly_visitors_trend`, nullable — `null` when there's no prior-
period data, not `0`, because "no data to compare" and "no change" are
different, honest answers). This closes a real gap flagged in both the
Milestone 5 and 6 reviews: KPI trend was omitted from every real KPI
because nothing had historical data to compute one from. Now one does.
The frontend's `map-summary-to-kpis.ts` was updated to actually render
it — verified live (see DEVLOG).

**No repository layer**, consistent with
[[0004-backend-foundation]](0004-backend-foundation.md)'s reasoning —
still nothing to abstract. **No `WorkspaceService`** — there's no
Workspace CRUD endpoint this milestone (workspace creation/management
is reasonably an onboarding-flow concern for a future milestone, not
implied by "Domain & Data Platform"), so a service with no controller
consumer would be dead code the moment it shipped.

## API

`Route::apiResource('sites', SiteController::class)` and
`apiResource('posts', PostController::class)` — full CRUD (`index`,
`show`, `store`, `update`, `destroy`), all through the same
`ApiResponse` envelope Milestone 6 established. `IndexSitesRequest`/
`IndexPostsRequest` validate optional `workspace_id`/`site_id` and
`status` filters; `Store*`/`Update*Request` pairs validate creation
and partial-update input, with `workspace_id`/`site_id` deliberately
**excluded** from the `Update` requests — moving a site between
workspaces (or a post between sites) is an ownership-transfer
operation, not a plain attribute edit, and isn't supported yet.
Verified directly: a client attempting to smuggle a different
`workspace_id` into a `PUT /sites/{id}` body has it silently ignored
(not validated against, not applied) — see `SiteCrudTest`.

No `authorize()` calls in any controller — see `SitePolicy`'s own doc
comment. Wiring policies into routes without an authenticated user to
check would 403 every request today; that's Milestone 8's job.

## Security

**Mass assignment** — every new model (`Workspace`, `AnalyticsSnapshot`,
`PublishingJob`) uses explicit `$fillable`, same posture as Milestone
6. **Validation** — every write endpoint has a Form Request; no
inline `$request->all()` anywhere. **Authorization** — real logic
exists (`SitePolicy`, `PostPolicy`) and is unit-tested directly
(`PolicyTest.php`) against the actual Workspace/User membership model,
ahead of Milestone 8 wiring it into routes — proven correct before
it's load-bearing, not written blind when auth lands. **CORS/headers/
secrets** — unchanged from Milestone 6, still correct, re-verified
this milestone (see Validation).

## Observability

No new logging/tracing infrastructure this milestone — Milestone 6's
`AssignRequestId`/`SecureHeaders` middleware and the
`ApiExceptionHandler` choke point are unchanged and cover the new CRUD
endpoints automatically (verified: every new route still returns
`X-Request-Id`). `SiteConnected` being dispatched for real (via
`LogSiteConnected`) means a structured log line (`Log::info('Site
connected', [...])`) now actually fires on site creation — the first
domain event this project has ever really emitted, not just declared.
OpenTelemetry integration point is unchanged from
[[0004-backend-foundation]](0004-backend-foundation.md) — still
documented, still not implemented.

## Performance

**N+1 risk, identified but not yet triggered.** `SitePolicy`/
`PostPolicy` methods call `$site->workspace->hasMember($user)`, which
lazy-loads the `workspace` relation and then runs a *second* query
inside `hasMember()`. Calling this once (a single `show`/`update`
check) is fine. Calling it in a loop — e.g. once Milestone 8 wires
`can:view` into `index` and needs to filter a list of sites by
authorization per-request — would be a real N+1. Not fixed now because
nothing calls it in a loop yet; the fix when it matters is eager-
loading (`Site::with('workspace.users')`) before the loop starts, not
a change to the policy methods themselves. Flagged here so Milestone 8
inherits the awareness, not the bug.

**Pagination — not implemented, a real gap at real scale.** `sites`/
`posts` `index()` return the full result set. Fine at today's seeded
volume (single digits to low tens of rows); genuinely wrong past a few
hundred posts. Not built this milestone because there's no real
multi-tenant data volume yet to size page limits against — tracked in
Future Backlog, not ignored.

**Caching / queues — documented, not implemented.** `PublishingJob`
and `SiteConnected`/`LogSiteConnected` are exactly the seams a real
queue (Redis + Laravel Horizon, or database queue driver already
configured — see `backend/.env`'s `QUEUE_CONNECTION=database`) would
attach to; nothing dispatches onto a queue yet since nothing is slow
enough to need one. Dashboard summary's aggregate queries are cheap at
today's scale (a handful of sites/posts); caching them would be
premature — worth revisiting once a workspace can realistically have
hundreds of sites.

## Rejected Alternatives

**Single `workspace_id` column on `User` instead of a pivot table.**
Simpler, but wrong for a real SaaS — see the Domain Model table above
and the pivot migration's own doc comment. Rejected before writing any
code, not discovered as a mistake afterward.

**A raw analytics *events* table (page views, one row per visit)
instead of `AnalyticsSnapshot`.** Far more realistic long-term, and
explicitly the future Analytics milestone's actual job. Building it
now, ahead of a real tracking pixel/beacon or WordPress-side plugin to
populate it, would mean guessing at a schema with no real producer to
validate it against. `AnalyticsSnapshot` (one row per site per day) is
the smallest schema that lets `DashboardService` compute a real trend
today without pretending to solve a problem (event-level analytics)
this milestone was never scoped to solve.

**A single polymorphic `authorable`/ownership column instead of
Workspace-scoped policies.** Considered making `SitePolicy` check a
direct "owner" relationship on `Site` rather than routing through
`Workspace`. Rejected: `Site` doesn't have its own membership list —
authorization for a tenant's resources should flow through the
tenant, not be duplicated per resource type. `PostPolicy` reusing the
exact same `$post->site->workspace->hasMember()` pattern (rather than
a separate post-level ownership check) is the direct consequence of
that choice, and it's why adding a *third* ownable resource later only
needs the same one-line pattern, not a new ownership concept.

## Future Evolution

- Milestone 8 (Authentication): wires `auth:sanctum` around the routes
  above, adds `->authorize()` calls using the policies this milestone
  already wrote and tested, and resolves "current workspace" from the
  authenticated user (likely the first workspace they belong to, or an
  explicit switcher — a real product decision for that milestone, not
  this one).
- Milestone 9 (WordPress Integration): the real OAuth/API-key
  connection flow that creates `Site` rows from an actual WordPress
  handshake; `ServiceUnavailableException`
  ([[0004-backend-foundation]](0004-backend-foundation.md)) becomes
  real the moment that flow calls a real WordPress REST endpoint.
- A future Analytics milestone: real event-level tracking, almost
  certainly *feeding into* `AnalyticsSnapshot` as a daily rollup rather
  than replacing it outright — this milestone's snapshot table was
  designed as a plausible aggregation target, not a dead end.
- A future AI milestone: designs and adds the AI Jobs table this ADR
  explicitly deferred, informed by a real provider integration instead
  of a guess.
- Workspace deletion, pagination, and the N+1 authorization risk above
  are all named, tracked future work — see
  `docs/ENGINEERING_JOURNAL.md`'s Future Backlog.
