# Milestone 11 Report

## Date

2026-07-14

---

## Objective

Introduce the application's asynchronous processing platform using
Laravel Queues, moving long-running and external operations out of
synchronous HTTP requests while establishing a production-ready job
architecture future milestones (AI generation, notifications,
scheduled maintenance, imports/exports, webhook processing) extend
without architectural change. By the end of this milestone, no
expensive operation should require the user to wait for an HTTP
request to finish.

---

## Executive Summary

Milestone 11 is complete and, on independent review, sound. The
repository review confirmed the infrastructure this milestone needed
was already provisioned and waiting: `QUEUE_CONNECTION=database` has
been set since Milestone 1, and Laravel's `jobs`/`job_batches`/
`failed_jobs` tables have existed since the same default migration —
no new migration was needed. The actual work was architectural:
converting one real, already-shipped synchronous operation
(`ContentSyncService::sync()`) into a dispatched job, and building the
pattern generalizably enough that a second, genuinely different job
(`RefreshSiteMetadataJob`) proves reuse rather than asserting it.

Two seams named in advance by prior milestones' own ADRs were closed
exactly as predicted, not reinvented: Milestone 10's ADR described the
content-sync-to-async migration as "a worker calling it instead of a
controller calling it inline," and that is precisely what shipped.
Milestone 10.1 left System Health's `backgroundQueue` metric
hardcoded specifically because no real queue existed to report on —
`QueueHealthChecker` now reports genuinely real `pending`/`failed`
counts, verified live against an actual failed job in a real browser
session, not merely asserted in isolation.

A real, non-obvious Laravel queue-internals finding shaped this
milestone's own test design: the `sync` queue driver (this project's
test-environment default) doesn't retry on failure the way a real
worker does — it executes once and re-throws, meaning a pre-existing
M10 test needed zero changes to keep passing after the async
conversion, a genuine and verified coincidence of the test driver's
own behavior, documented in the Engineering Journal rather than
silently relied on.

One deliberate, judgment-call scope decision runs through this
milestone: `RefreshSiteMetadataJob` was built and is genuinely reused
(by a new daily Scheduler task), but was *not* wired into the existing
synchronous "Refresh Metadata" button — that action is fast and
bounded enough that immediate feedback remains the correct UX, and
force-fitting a new abstraction into every place it technically could
apply would have been reuse for its own sake, not for a real need.

---

## Architecture Decisions

**Queue driver — `database`, unchanged from what was already
configured.** No new infrastructure dependency; a config change is
all a future move to Redis/SQS would require, since nothing in this
milestone's code assumes `database` specifically (`QueueHealthChecker`
explicitly branches on the configured driver rather than hardcoding
table queries).

**Where the `Syncing` status transition lives — two places,
deliberately.** `ContentSyncController::sync()` sets it synchronously
before dispatch (instant feedback ahead of any queue lag);
`ContentSyncService::sync()` also sets it at the start of its own
execution (so a retried attempt re-enters a consistent state rather
than showing a stale `Error` badge between attempts). See
`docs/adr/0009-background-job-platform.md` for the full reasoning —
this was scrutinized specifically for redundancy and found to cover
two genuinely distinct windows of time.

**Fast-fail credential check kept in both the controller and the
service.** The controller's check is a cheap synchronous UX
short-circuit (no credential → immediate `422`, not a delayed
`202`-then-poll-to-discover-failure); the service's check is
defense-in-depth against the credential being removed between dispatch
and execution. Deliberate duplication of a security-adjacent
invariant, the same multi-layer posture already established for
credential encryption in Milestone 9.

**`RefreshSiteMetadataJob` reused by the Scheduler, not the manual
button.** See Executive Summary — a real, documented trade-off, not
an oversight.

---

## Queue Design

```
App\Jobs\SyncWordPressPostsJob      — wraps ContentSyncService::sync()
App\Jobs\RefreshSiteMetadataJob     — wraps SiteConnectionService::refreshMetadata()
```

Both: `ShouldQueue` + `ShouldBeUnique` + `SerializesModels`. `$tries = 3`,
`backoff() = [10, 30, 60]` seconds, `uniqueId()` keyed per-`Site`,
`uniqueFor = 300` seconds. A `failed()` handler on both marks the site
`Error` with the failure reason — a defensive safety net, since the
underlying services already mark `Error` on their own final failed
attempt.

**`ContentSyncController::sync()`** — authorize → fast-fail credential
check → `Site.status = Syncing` → `SyncWordPressPostsJob::dispatch($site)`
→ `202 Accepted` with `{status: "queued", site_id}`. `SyncResultResource`
(the old synchronous HTTP-response wrapper) is deleted; `SyncResultDTO`
is unchanged and still returned internally by the service.
`GET /sites/{site}/sync-status` (built Milestone 10, unchanged) is the
completion-detection primitive the frontend polls.

**`QueueHealthChecker`** — `App\Support\QueueHealthChecker`, mirroring
the existing `DatabaseHealthChecker`. Reads `jobs`/`failed_jobs`
directly via `DB::table()`. `pending` is `null` for any
non-`database` driver (a `jobs`-table count wouldn't reflect Redis/SQS
reality); `failed` is meaningful for any driver using
`database-uuids` as its failed-job store (this project's default).
`queueStatus` derives to `degraded` when any failed job exists.

**Scheduler** — one new task, `routes/console.php`:
`Schedule::call(...)->daily()->name('refresh-connected-site-metadata')->withoutOverlapping()`,
dispatching `RefreshSiteMetadataJob` for every connected site across
every workspace. The Scheduler's cross-workspace enumeration is not a
tenant-isolation violation — each dispatched job instance still only
ever touches the one `Site` it was given; see the ADR's Security
section for the full reasoning on why this distinction holds.

---

## Validation

- `php artisan test`: **103/103 passing** (up from 95) — 8 new tests
  in `tests/Feature/BackgroundJobsTest.php` (real dispatch assertions,
  real uniqueness-locking against the `database` driver, `failed()`
  handler correctness, job configuration checks, real queue-metrics
  correctness including a genuinely inserted `failed_jobs` row, and a
  Scheduler-registration check), plus `tests/Feature/ContentSyncTest.php`
  updated for the new async response contract with zero loss of
  coverage (idempotency, update detection, trash-skipping, mapper
  correctness, authorization, and workspace isolation all still
  independently verified).
- `./vendor/bin/pint --test` on every new/modified backend file: pass.
- `npm run typecheck` / `npm run lint` / `npm run build`: all pass.
- Live browser verification with a **real** `php artisan queue:work`
  process running alongside the app servers (not `Queue::fake()`, not
  the `sync` test driver): clicked "Sync Content" against the seeded
  environment's genuinely unreachable `acmeblog.example.com` domain,
  watched a real worker pick up and process the job, watched the site
  correctly land in `Connection Error` with the real WordPress
  connection-exception message, and watched `GET /system-health`
  reflect `1 failed` / `degraded` status immediately after — all
  matching the intended async behavior with zero manual intervention
  beyond the initial click.
- `axe-core` audit against the site detail page (before and after
  triggering a sync) and the dashboard: **zero violations** on all
  three checks.
- Zero console/page errors across the full verification session.

---

## Production Readiness

The async content-sync path is genuinely production-shaped: real
retry/backoff, real per-resource uniqueness locking (verified against
the actual `database` driver, not a fake), real failure handling that
correctly updates domain state, and a verified security property
(credentials never enter the queue payload). System Health's queue
metrics are real, not cosmetic. The one piece of infrastructure this
milestone explicitly does not provide — a supervised, always-running
`queue:work` process — is named, not silently assumed: nothing in any
environment this project runs in today keeps a worker alive or
restarts it after a crash. That's real, deferred deployment work
(Milestone 19), not an oversight in this milestone's own scope.

---

## Technical Debt Resolved

- **"Content sync is fully synchronous"** (named in Milestone 10's own
  ADR as the seam this milestone would close) — resolved exactly as
  predicted.
- **"System Health's `backgroundQueue` is an honest, hardcoded
  placeholder"** (named in Milestone 10.1) — resolved; real metrics,
  verified live.
- **`SyncResultResource`** — deleted as genuinely dead code once the
  controller stopped constructing an HTTP response from it (no
  functionality lost; `SyncResultDTO` itself, still returned
  internally by the service, is unchanged).

---

## Deferred Work

- **Process supervision for `queue:work`** — no Supervisor (or
  equivalent) config exists in any environment yet; deferred to
  Milestone 19 (Cloud Deployment & Security Hardening).
- **`RefreshSiteMetadataJob` wired into the manual "Refresh Metadata"
  button** — deliberately not done; see Architecture Decisions.
- **Job batching (`job_batches`)** — provisioned, unused. No current
  feature dispatches a set of jobs needing combined completion
  tracking; a real candidate once a bulk "sync every site in a
  workspace" action exists.
- **Real-time completion push (SSE/WebSockets)** — polling was chosen
  deliberately for this milestone; the seam is isolated to
  `useSite`/`useSyncStatus` so a future push mechanism replaces it
  without touching any other layer.
- **`PublishingJob`/`PublishingService::schedule()` (Milestone 7's
  placeholder)** — not wired into a real queue consumer this
  milestone. Publishing (writing WP Studio content back to WordPress)
  remains future scope; this milestone's job platform is exactly the
  infrastructure that future Publishing work will dispatch onto.
- **Lightweight completion notifications** — reviewed per the brief's
  own instruction, deliberately not built. The polling-driven status
  badge and `SyncSummary` card already surface completion on the page
  the user triggered it from; a separate toast/notification-store
  entry would duplicate that signal without a named need it serves
  today.

---

## Risks

- **No supervised worker in any current environment** — a dispatched
  job with `QUEUE_CONNECTION=database` and no running `queue:work`
  process sits in the `jobs` table indefinitely. This is an
  operational/deployment gap, not a code defect; documented explicitly
  in `SESSION_HANDOFF.md` so a future session doesn't mistake "nothing
  happened" for a bug.
- **`sync` queue driver's failure semantics are test-environment-only
  behavior** — the `sync` driver's immediate-rethrow-on-failure
  behavior (see Engineering Journal) means a test passing doesn't
  automatically prove the `database`/production driver's retry
  behavior is correct; the dedicated `BackgroundJobsTest` cases were
  written specifically to exercise real `database`-driver behavior
  (uniqueness locking, real queue-table counts) rather than relying on
  `sync`-driver coincidences for everything.
- **No new security surface** — every new endpoint/job reuses existing
  authorization (`SitePolicy`), and the credential-serialization
  property was independently verified, not assumed.

---

## Future Queue Consumers

- **AI (Milestone 14)** — the direct third consumer of this job
  pattern: an AI generation request has the exact shape (external API
  call, unpredictable latency, needs retry/backoff) this milestone
  built the pattern for.
- **Notifications** — reviewed this milestone, deliberately not built
  as a distinct backend domain (see Deferred Work). A future real
  notification feature, once a product decision exists about what
  constitutes one, would likely dispatch its own job on completion of
  any of this milestone's jobs.
- **Imports/Exports** — same shape as content sync (external or bulk
  data operation, bounded but potentially slow) — a natural third
  `ShouldQueue` implementation following the same contract.
- **Scheduler** — already a real consumer this milestone (the daily
  metadata-refresh task); the same `Schedule::call()` pattern extends
  trivially to a periodic content re-sync (cheap today, thanks to
  hash-based idempotency) or cleanup tasks, without inventing a new
  scheduling mechanism.

---

## Recommendation for Milestone 12

Per `docs/ROADMAP.md`, Milestone 12 (Storage & Media) is next in
sequence. This milestone's job platform is directly relevant to it:
media processing (thumbnailing, format conversion) is exactly the kind
of operation that belongs in a queued job rather than a blocking
request, following the identical `ShouldQueue` pattern established
here. Recommend proceeding with Milestone 12, using this milestone's
job architecture as the template for any async media-processing needs
it surfaces, rather than inventing a parallel pattern. Waiting for
explicit approval before starting, per this milestone's own stop
condition.
