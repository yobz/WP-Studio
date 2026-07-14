# 0009 — Background Job & Queue Platform

**Status:** Accepted (Milestone 11)

## Decision

Introduce Laravel's queue system as this platform's asynchronous
processing infrastructure — not a one-off feature, but a reusable job
pattern future milestones (AI generation, notifications, scheduled
maintenance, imports/exports) build on without architectural change.
Convert `ContentSyncService::sync()` from a request-blocking
synchronous call into a dispatched job (`SyncWordPressPostsJob`).
Build a second job (`RefreshSiteMetadataJob`) to prove the pattern
generalizes, consumed by a new Laravel Scheduler task rather than the
existing manual "Refresh Metadata" button. Replace System Health's
hardcoded queue placeholder with real metrics read from Laravel's own
`jobs`/`failed_jobs` tables. Use the `database` queue driver — already
configured since Milestone 1, no new infrastructure dependency.

## Context

**Where this sits in the project.** Milestone 10 built content
synchronization as a synchronous, single-request operation, explicitly
naming the async conversion as future work: "`ContentSyncService::sync()`
is unchanged by a future move to a queued job — a worker calling it
instead of a controller calling it inline is the entire migration"
(`docs/adr/0008-content-synchronization.md`, Future Evolution).
Milestone 10.1 left `SystemHealthService`'s `backgroundQueue` metric
hardcoded for the same reason: no real queue existed yet to report on.
This milestone is where both of those named seams become real.

**What already existed, unmodified.** `QUEUE_CONNECTION=database` has
been set in `.env`/`.env.example` since Milestone 1. The `jobs`,
`job_batches`, and `failed_jobs` tables have existed since Laravel's
default `0001_01_01_000002_create_jobs_table.php` migration — also
since Milestone 1. No new migration was needed for this milestone;
the infrastructure was provisioned and waiting.

## Alternatives Considered

**Queue driver — `database` vs. Redis/SQS.** Redis or a managed queue
service (SQS) are the more common production choices for real
throughput. Chosen `database`: this project's own established pattern
(SQLite locally, deferring the "real" production database choice to
Milestone 19 — `docs/adr/0004-backend-foundation.md`) already accepts
a zero-additional-infrastructure local story over a production-shaped
one for now. `database` needs no new service to install/run, works
identically in every environment this project already targets, and
Laravel's `config('queue.default')` makes switching to Redis/SQS later
a config change, not a code change — every job class, the dispatch
call sites, and `QueueHealthChecker`'s driver-branching logic are
already written to not assume `database` specifically.

**Where the `Syncing` status transition lives — controller only vs.
controller *and* service.** The simpler design sets `Site.status =
Syncing` once, in the controller, before dispatch. Rejected as
incomplete: a job that fails and retries would leave the site showing
a stale `Error` badge (set by the previous failed attempt) during the
gap before the next retry actually starts running. Chosen: the
controller sets `Syncing` synchronously for instant feedback (a queued
job might not run for a moment); `ContentSyncService::sync()` *also*
sets `Syncing` at the start of its own execution, so every attempt —
initial or retried — re-enters a consistent "in progress" state. Two
writes to the same field, each covering a real window of time the
other can't see, not duplicated logic.

**Fast-fail credential check — controller only vs. job only.**
Considered removing the credential-existence check from the
controller entirely and letting the job's own `handle()` (which calls
`ContentSyncService::sync()`, which already checks) be the only
gate — simpler, no duplication. Rejected: a user with an obviously
disconnected site (no credential) deserves an immediate `422`, not a
`202 Queued` followed by a delayed failure discovered only by polling.
Kept both checks deliberately — the controller's is a fast UX
short-circuit; the service's is defense-in-depth against the
credential being removed between dispatch and execution (a real,
if narrow, race condition). The same multi-layer posture Milestone 9
already established for credential security, applied here to a
timing concern instead of an encryption one.

**Converting `verifyConnection`/`refreshMetadata`'s existing manual
button to async, alongside content sync.** Both call the same
underlying `SiteConnectionService::syncFromWordPress()` content sync
now bypasses via a job. Considered converting the manual "Refresh
Metadata" button to dispatch `RefreshSiteMetadataJob` too, for
consistency. Rejected: that action is a single, bounded WordPress
request (5–10s worst case, per `docs/adr/0007-wordpress-integration-architecture.md`),
not content sync's paginated, potentially-many-request fetch — a user
clicking it wants an answer in a couple of seconds, and routing it
through a queue adds latency (dispatch → worker pickup → poll) for no
real benefit. `RefreshSiteMetadataJob` is still genuinely reused — by
the new Scheduler task below — proving the job pattern generalizes
without forcing every call site through it regardless of fit.

**A new Scheduler task vs. no scheduling this milestone.** The brief
explicitly asked whether scheduled tasks belong in this milestone.
Chosen: one daily task, refreshing metadata for every connected site
across every workspace, dispatching `RefreshSiteMetadataJob` per site.
This is the concrete "periodic background re-verification of every
connected site" scenario `docs/adr/0007-wordpress-integration-architecture.md`
already predicted as `SiteConnectionService`'s natural evolution.
Scoped to one task, not also a periodic content re-sync — content
sync's own hash-based idempotency already makes a periodic re-run
cheap (unchanged posts are skipped, not rewritten), so adding it later
is a one-line `Schedule::call()` addition, not a new pattern; not
built now to keep this milestone's scope legible.

## Job Design

```
App\Jobs\
├── SyncWordPressPostsJob.php    — wraps ContentSyncService::sync()
└── RefreshSiteMetadataJob.php   — wraps SiteConnectionService::refreshMetadata()
```

Both implement `ShouldQueue` + `ShouldBeUnique` + `SerializesModels`,
and share the same shape:

- **Retries:** `$tries = 3`.
- **Backoff:** `[10, 30, 60]` seconds — increasing delay between
  attempts, not a fixed interval, so a transient failure gets an
  immediate retry while a persistent one backs off rather than
  hammering an already-struggling external site.
- **Uniqueness:** `uniqueId()` returns a string keyed on the specific
  `Site`, `uniqueFor = 300` seconds. Laravel's unique-job locking (a
  cache lock, verified against the real `database` driver + `array`
  cache in this milestone's own tests, not just asserted structurally)
  means a second dispatch for the same site while one is already
  queued is a no-op — the concrete mechanism preventing two concurrent
  syncs of the same site from racing each other.
- **`failed()` handler:** marks the site `Error` with the exception
  message, a defensive safety net — `ContentSyncService`/
  `SiteConnectionService` already mark `Error` on their own final
  failed attempt (see Content Synchronization Migration below), so
  this is redundant in the common case and only load-bearing if a
  failure occurs somewhere the underlying service didn't already
  handle.
- **`SerializesModels`:** the job's constructor takes the full `Site`
  model, but only its class name and primary key are actually
  persisted into the queue payload — see Security below.

## Content Synchronization Migration

```
Before (Milestone 10):  HTTP Request → ContentSyncService::sync() → SyncResultDTO → HTTP Response
After (Milestone 11):   HTTP Request → credential check → Site.status = Syncing → dispatch job → 202 Accepted
                         (async)      Worker → SyncWordPressPostsJob → ContentSyncService::sync() → Site.status updated
```

`ContentSyncController::sync()` no longer calls `ContentSyncService`
directly — it authorizes, fast-fails on a missing credential, sets
`Syncing`, dispatches, and returns `202` with `{status: "queued",
site_id}`. `SyncResultResource` (the old synchronous-response wrapper)
is deleted — nothing constructs an HTTP response from a `SyncResultDTO`
anymore, though the DTO itself is unchanged and still returned by
`ContentSyncService::sync()` internally. `GET /sites/{site}/sync-status`
(built Milestone 10) is unchanged and is now the completion-detection
primitive the frontend polls — no new "job status" endpoint or table
was needed, since `Site.status`/`last_synced_at`/`connection_error`
already answer "is this done, and how did it go."

## Queue Health

`App\Support\QueueHealthChecker` (mirrors `DatabaseHealthChecker`'s
shape) queries `jobs`/`failed_jobs` directly via `DB::table()` — no
new Eloquent model, consistent with treating these as framework-owned
tables, not domain models. Reports `pending` (only meaningful for the
`database` driver — `null` for any other configured driver, since a
`jobs`-table count wouldn't reflect Redis/SQS reality), `failed`
(meaningful regardless of driver, since `failed_jobs` is written by
Laravel's own failure pipeline for any driver using the
`database-uuids` failed-job store), `driver`, and `oldest_pending_seconds`.
`queueStatus` is derived (`degraded` if any failed job exists,
`operational` otherwise) — a real signal, not asserted; verified in
this milestone's own tests by inserting a real row into `failed_jobs`
and confirming the endpoint reflects it, and in live browser
verification against a genuinely failed sync job.

## Frontend

`useSite`/`useSyncStatus` gained a `refetchInterval` that polls every
2 seconds *only* while the underlying resource's status is `syncing`,
stopping automatically once it settles — "Queued → Processing →
Completed → Refresh" without WebSockets, and without polling
indefinitely once nothing is happening (the brief's explicit "do not
poll aggressively" constraint). `SyncButton` no longer displays
`created`/`updated`/`skipped` counts (that response shape no longer
exists) — it shows "Sync queued" immediately, then the site's own
`StatusBadge` (already supporting a `syncing` state since Milestone 5)
and `SyncSummary` card reflect live progress via the polling hooks.
`SiteDetail` watches `site.status` for a transition *away from*
`syncing` and invalidates the site's posts query at exactly that
point — the one piece of manual cache coordination the built-in
polling doesn't cover on its own, since polling refetches the query it's
attached to, not related queries.

**Prepared for, not built: real-time push.** Polling was chosen
deliberately over Server-Sent Events or WebSockets for this milestone
— the brief explicitly asked for the architecture to accommodate a
future switch without implementing one now. The seam is already in
place: `useSite`/`useSyncStatus` are the only two places that would
change (swapping `refetchInterval` for a subscription that calls
`queryClient.setQueryData()` on a push event) — no component, no
mutation, and no backend contract would need to change.

## Security

- **Credentials never enter the queue payload.** Verified directly
  (not assumed): `SerializesModels` persists an Eloquent model in a
  job's payload as a class name + primary key only, re-fetching the
  full model from the database on unserialize. `Site.credential` is a
  lazily-loaded relation never eager-loaded onto the job's `Site`
  property, so the encrypted `application_password` is never part of
  the `jobs` table's `payload` column at any point — the same
  guarantee `docs/adr/0007-wordpress-integration-architecture.md`
  already established for HTTP responses, now confirmed for queue
  storage too.
- **Tenant isolation is enforced before dispatch, not inside the
  job.** `ContentSyncController::sync()` still calls
  `$this->authorize('view', $site)` before ever constructing the job —
  a job only ever processes the one `Site` it was constructed with,
  and that `Site` was already authorization-checked at dispatch time.
  A job has no "current workspace" concept to re-derive (it doesn't
  run inside a request), which is correct: the isolation guarantee is
  "a job never processes data outside the `Site` it was given," not
  "a job can independently prove workspace membership" — the latter
  would be redundant, since dispatch already required it.
- **The Scheduler's cross-workspace enumeration is not a tenant-
  isolation violation.** The daily metadata-refresh task iterates
  every connected site across every workspace to decide *which* jobs
  to dispatch — a legitimate, expected shape for scheduled
  maintenance (the same way a database backup job touches every
  tenant's data). Each dispatched `RefreshSiteMetadataJob` instance
  still only ever touches the one `Site` it was given.

## Performance

No new N+1s introduced. `SyncWordPressPostsJob`/`RefreshSiteMetadataJob`
each operate on exactly one `Site`; the Scheduler's site enumeration is
a single `Site::query()->connected()->each()` call, not a query per
site. `QueueHealthChecker`'s two `DB::table()->count()` calls run only
when `GET /system-health` is requested, not per-request overhead
elsewhere.

**Named, accepted limit: no job batching.** `job_batches` (Laravel's
default, provisioned since Milestone 1) is unused — nothing in this
milestone dispatches a set of jobs that need combined
all-succeeded/any-failed tracking. A real candidate the moment a
"sync every site in a workspace at once" bulk action exists; not
built speculatively ahead of that need.

## Rejected Alternatives

**A dedicated `sync_jobs`/job-status table for tracking dispatch → completion.**
Considered building a table specifically to answer "what's the status
of the sync I just triggered." Rejected: `Site.status`/`last_synced_at`/
`connection_error` (all already existing since Milestones 9–10)
already answer exactly that question, and `GET /sites/{site}/sync-status`
already exposes it. A dedicated job-tracking table would duplicate
state that's already correctly maintained, the same reasoning
`docs/adr/0007-wordpress-integration-architecture.md` already applied
to rejecting a connection-history/audit table.

**Wiring `RefreshSiteMetadataJob` into the manual "Refresh Metadata"
button.** See Alternatives Considered above — a deliberate, named
non-change, not an oversight.

## Future Evolution

- **Milestone 14 (AI-Assisted Content Generation):** the natural third
  consumer of this job pattern — an AI generation request is exactly
  the shape (external API call, unpredictable latency, needs
  retry/backoff) this milestone built the pattern for.
- **Milestone 18 (Observability):** `QueueHealthChecker`'s real
  pending/failed counts and `ContentSynced`'s existing domain-event
  dispatch are both already-real signals a future structured-logging/
  tracing integration attaches to, not new instrumentation to invent.
- **Milestone 19 (Cloud Deployment & Security Hardening):** process
  supervision for `queue:work` (Supervisor or equivalent) is real,
  named, deferred infrastructure work — nothing in this environment
  keeps a worker running or restarts it after a crash today.
- **A future bulk "sync every site in a workspace" action** is the
  concrete trigger for finally using `job_batches` — deferred, not
  because it's hard, but because nothing needs it yet.
- **Real-time completion push (SSE/WebSockets)** replaces
  `useSite`/`useSyncStatus`'s polling without touching any other layer
  — the seam is already isolated to those two hooks.
