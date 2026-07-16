# Devlog

## 2026-07-16 — Milestone 14: AI-Assisted Content Generation

The first real AI provider integration, and the last named gap from
`docs/adr/0005-domain-model.md`'s deferred "AI Jobs" schema. Full
reasoning in `docs/adr/0012-ai-content-generation.md`; this entry is
the what.

**Two providers, one contract.** `App\Services\AI\AiClientContract`
has one method, `generate()`. `AnthropicMessagesClient` (official
`anthropic-ai/sdk`, `claude-opus-4-8`) and `GeminiClient` (raw HTTP
against Google's Generative Language API, following
`HttpWordPressClient`'s hand-rolled-client precedent rather than an
unverified community package) both implement it; `AppServiceProvider`
binds whichever `AI_PROVIDER` config names. Gemini support was added
mid-milestone, after the Claude integration was already built and
tested — the contract absorbed it as a pure addition, with zero
changes to `AiJobService`, the job, the controller, or the frontend.

**Async through the existing job platform, not a new one.**
`POST /api/v1/ai/generate` creates an `AiJob` row and dispatches
`GenerateAiContentJob` — same `tries: 3`/`backoff: [10, 30, 60]` shape
as `SyncWordPressPostsJob` (Milestone 11) — returning
`202 {status: "queued", job_id}` immediately.
`GET /api/v1/ai/jobs/{id}` is the poll endpoint; the frontend's
`useAiJob()` polls every 2s while pending/processing, the same
mechanism `useSyncStatus`/`useSite` already use. Non-retryable
failures (malformed response, bad credentials) resolve immediately via
`$this->fail()`; retryable ones (rate limits, connection failures,
5xx) bubble out of `handle()` so the queue worker's own retry/backoff
handles them — verified directly, not just reasoned about.

**`AiAssistantPreview` is real now.** The prompt textarea and
suggested-prompt chips shipped in Milestone 5; `Generate` was disabled
until today. Now: Generating (spinner, disabled inputs) → Completed
(result panel) or Failed (inline error, retry) — three new states, one
existing widget, zero new global state (`useGenerateContent`/
`useAiJob` hold everything TanStack Query already owns).

**A real external-API finding during live verification.** The Gemini
model this milestone first shipped with (`gemini-2.5-flash`) returned
a live `404` from Google — deprecated for new API keys, despite being
listed current in Google's own docs fetched the same session.
Distinguished from a credential problem by probing model IDs directly
against the key (never printing it) and observing `429`s — which only
happen after successful auth — on `gemini-2.0-flash`/`gemini-2.5-pro`.
Switched the default to `gemini-2.0-flash`. A full success-path live
demo was ultimately blocked by the account's free-tier daily quota,
not by this milestone's code — the entire pipeline (auth, queue
processing, real outbound HTTPS call, retry/backoff, typed exception
mapping, frontend polling, error UI) was verified live against the
real API up to that point; zero console errors, zero axe-core
violations. The completed-state UI remains covered by
`AiGenerationTest`'s fake-provider integration test. Full account in
`docs/ENGINEERING_JOURNAL.md`'s dated entry.

142 backend tests passing (up from 127).

## 2026-07-16 — Milestone 13: GraphQL Layer

A single read-only `/api/v1/graphql` endpoint, backing exactly the
case the roadmap named for this milestone — dashboard aggregation
with variable shape — not a wholesale REST replacement. Full reasoning
in `docs/adr/0011-graphql-layer.md`; this entry is the what.

**The Dashboard fires two requests on load instead of four.**
`dashboardOverview` composes summary, recent activity, and system
health — the three widgets that were always fetched together, on one
page, every time — into one GraphQL request. `analyticsPreview(range:)`
stays a second, independent query, since its range argument varies on
its own (switching the chart's time range shouldn't refetch the other
three widgets). Verified directly in a real browser via network
interception: exactly two `POST /api/v1/graphql` requests on Dashboard
load, zero legacy REST dashboard/analytics/system-health requests,
exactly one additional request when the range control changes.

**Resolvers delegate, they don't duplicate.** `DashboardOverview`/
`AnalyticsPreview` (`app/GraphQL/Queries/`) call the exact same
`DashboardService`/`AnalyticsService`/`SystemHealthService` methods
the REST controllers already call. Zero new aggregation logic exists
anywhere in this milestone — GraphQL is a new way to call proven code,
not a second implementation of it.

**The GraphQL route sits inside the existing middleware stack, not
beside it.** Lighthouse registers its own top-level `/graphql` route
by default; disabled that (`'route' => false`) and registered
`POST /api/v1/graphql` manually inside `routes/api_v1.php`'s existing
`auth:sanctum` → `ResolveCurrentWorkspace` group instead — the same
tenant isolation and session auth every REST route already has,
verified directly by a test creating data in a second workspace and
confirming `dashboardOverview` never reflects it.

**This project's second mandatory Architecture Drift Review did real
work.** Lighthouse makes exposing `Site`/`Post` as full GraphQL types
low-effort — reviewed and explicitly rejected, since both already have
complete, tested, policy-enforced REST CRUD and a second read/write
path would duplicate proven capability, not add value. Scope held
exactly where the review put it: one schema, two queries, zero
mutations, zero changes to any existing REST route.

**A stale framework cache silently broke package registration —
recognized immediately from a documented pattern, not re-debugged from
scratch.** After `composer require nuwave/lighthouse`, the package
didn't appear in `php artisan package:discover`'s output at all, and
`vendor:publish` found nothing to publish. Root cause: a stale
`bootstrap/cache/services.php` — the same OneDrive-synced-path
cache-staleness issue first documented in Milestone 6's Engineering
Journal, recurring for a new package. Recognized in under a minute
specifically because it was already a named, documented failure
pattern; fixed by deleting the cache file and letting Laravel
regenerate it.

**A genuine GraphQL semantics gap broke a live component — caught by
browser verification, not by typecheck/lint/build, all of which passed
cleanly on the broken code.** GraphQL enum fields serialize over the
wire as their schema **name** (`POST_PUBLISHED`), not the
`@enum(value: ...)` directive's internal value (`post-published`) —
easy to assume the opposite, since the directive's whole job is
mapping between the two. `RecentActivity`'s icon lookup, keyed on the
internal value since Milestone 5, received the wire name instead and
rendered `undefined` as a component — a React crash, live-tested via a
real login → Dashboard flow in a real browser, not caught by any
static check. Fixed by translating the wire name back to the internal
value at the exact boundary where GraphQL data enters the frontend
(`useDashboardOverview`'s `queryFn`), so every existing component
downstream needed no changes at all.

**Zero widget-component changes; five now-dead files removed.**
`KpiCards`, `RecentActivity`, `SystemHealth`, and `AnalyticsPreview`
required no changes — only their hooks' data source moved from REST to
GraphQL, the same "swap the hook, not the component" discipline this
project has followed since Milestone 6's first mock-to-real migration.
The REST-only frontend files these hooks previously used
(`dashboard.service.ts`, `analytics.service.ts`, `system-health.service.ts`,
`map-activity.ts`, `map-analytics-points.ts`) were deleted as
genuinely unused code — the backend REST endpoints they called remain
fully intact, tested, and available to any other consumer.

**Verified end-to-end against a real backend.** Backend: 127 Pest
tests passing (up from 120), including real workspace-isolation and
schema-level enum-validation coverage. Frontend: `typecheck`/`lint`/
`build` all clean. `axe-core`: zero violations on the GraphQL-backed
Dashboard.

## 2026-07-15 — Milestone 12: Media Platform & Storage

Every file this application stores — WordPress featured images today,
avatars/AI images/attachments/reports later — now goes through one
reusable Media domain instead of each feature inventing its own
storage code. Full reasoning in `docs/adr/0010-media-platform.md`;
this entry is the what.

**This milestone introduced a mandatory step for every milestone from
here forward: an Architecture Drift Review, done before writing any
code.** Confirmed the codebase was genuinely greenfield for a Media
domain (no pre-existing `Media`/`Attachment`/`Upload` code anywhere)
and caught one naming-adjacent risk worth documenting rather than
fixing: `Site.storage_used_mb`/`storage_limit_mb` (Milestone 6)
describe the *remote WordPress site's* disk usage, not WP Studio's own
storage — easy to conflate, now explicitly disambiguated.

**One `Media` table, polymorphically attachable, not a
WordPress-specific or upload-specific mechanism.** `mediable_type`/
`mediable_id` + `collection` means any future producer attaches to the
same table without a schema change — the brief's actual requirement
("no feature should manage files independently"), not just "add
upload." Workspace-scoped directly (not derived transitively through
`mediable`, since a library upload may have no parent yet), hash-
deduplicated (sha256 — reuses an existing `storage_path` instead of
writing identical bytes twice), disk-abstracted end to end
(`MediaService` is the only class that calls `Storage::disk(...)`, no
raw filesystem calls anywhere in this feature).

**Extends Content Sync, doesn't sit beside it.**
`WordPressPostMapper::map()` now reads `featured_media` from the raw
WordPress payload — folded into the existing change-detection hash, so
an image-only change now correctly produces an `Updated` sync outcome
instead of a false `Skipped`. `syncFeaturedImage()` (WordPress-post-
specific logic, not the generic `ContentSyncService` orchestrator)
dispatches a new `DownloadMediaJob` — full Milestone 11 job shape:
3 tries, `[10, 30, 60]`s backoff, per-post uniqueness, `SerializesModels`
— with a guard that no-ops when the same WordPress media ID is already
attached, and handles removal synchronously (no job needed for a
delete). `WordPressClientContract` gained one new method, `fetchItem()`,
generic enough for any future single-resource WordPress fetch, reusing
`HttpWordPressClient`'s existing internals rather than duplicating
them.

**A real defect this milestone's own test suite caught before it
shipped.** Added genuine DB-level unique constraints on the
polymorphic attachment slot and on `(site_id, source_id)` during
implementation — then watched a test for "replace a post's featured
image on re-sync" fail with a real `QueryException`. Root cause:
`SoftDeletes` makes a row logically gone but physically still present,
and a unique index has no concept of `deleted_at` — soft-deleting the
old attachment and inserting the new one collided. Fixed by moving
both invariants into the service/mapper layer instead (where the
actual business decision already lived) and, while investigating,
discovered `posts`' own `(site_id, wordpress_post_id)` unique index
carries the identical, apparently-never-exercised tradeoff — now a
named, documented risk instead of a silent one.

**A deliberate deviation from the brief's own example list, not an
oversight.** The brief named `MediaDTO` as an expected layer. Skipped
it: `Media` is a real, persisted Eloquent model rendered through an
API Resource, the same shape `Post`/`Site` already use — a DTO
mirroring its columns 1:1 would be an unjustified extra layer with no
data it doesn't already have. Same category of judgment call Milestone
11 made about not wiring `RefreshSiteMetadataJob` into the manual
button — following established precedent over a literal instruction
when they conflict.

**Storage is one env var away from S3/R2/Spaces.** `MEDIA_DISK`
(default `public`) is deliberately independent of `FILESYSTEM_DISK` —
so the app's generic default disk changing for some unrelated purpose
can't accidentally make media private (or vice versa). The `s3` disk
and `AWS_*` env vars have existed since Laravel's own Milestone 1
defaults; switching disks requires zero code changes, the actual
"configuration change, not a code change" property the brief asked
for.

**Frontend: a real Media Library, and thumbnails where posts already
live.** `/media` — grid/list toggle, upload (a new `apiUpload()`
sibling to `apiFetch()` in `api-client.ts`, omitting the JSON
`Content-Type` header so the browser sets its own multipart boundary),
a preview dialog with alt-text editing and delete. `PostsTable`/
`PostDetail` render a featured-image thumbnail when present, zero
change to either component's existing async states.

**A real, novel accessibility defect, found and fixed during this
milestone's own verification — not merely audited after the fact.**
A destructive-variant Delete button placed inside the preview dialog's
`DialogFooter` failed WCAG AA contrast (4.24:1 against a 4.5:1
threshold) — the footer's semi-transparent `bg-muted/50` background
composites differently than the plain page backgrounds this app's two
other destructive buttons already sit on safely. This exact
combination had never existed anywhere else in the app. Fixed by
relocating the button out of the footer, not by overriding the shared
`Button` component's color tokens for one instance.

**Verified against a real backend, not a mock.** Backend: 120 Pest
tests passing (up from 103) — real disk writes via `Storage::fake()`,
real hash-based dedup verified against actual storage paths, a real
featured-image download/replace/remove cycle against faked WordPress
HTTP responses (including the defect above, caught live), real
per-post job uniqueness against the `database` driver. Frontend:
`typecheck`/`lint`/`build` all clean, including the new `/media` route
in the production build. Live browser verification (real
`php artisan serve` + `queue:work` + `npm run start`): uploaded a real
file, saw it in the grid, opened the preview, edited and saved alt
text, switched views, deleted it, watched the library correctly return
to its empty state — zero console errors throughout. `axe-core`: zero
violations across the Media Library, the preview dialog, the
Dashboard, and the Posts pages (after the contrast fix above).

**Playwright/Next.js click-navigation quirk, re-encountered, already
documented — worth re-flagging.** A verification script using
`locator.click()` on a Next.js `<Link>` silently failed to navigate
(the URL never changed) — the exact gotcha `SESSION_HANDOFF.md`
documented back in Milestone 11 ("needs `page.goto()` or a manual
URL-polling helper, not `page.waitForURL()` with its default
`waitUntil: 'load'`"). Switching the verification script to
`page.goto()` for the specific URL resolved it immediately — a
reminder that this is a real, recurring interaction quirk worth
checking first the next time a Playwright click-then-navigate step
behaves unexpectedly, before assuming an app defect.

## 2026-07-14 — Milestone 11: Background Job & Queue Platform

No expensive operation blocks an HTTP request anymore. Content sync —
converted to a real, dispatched-and-polled async job — is the first
consumer; the job platform itself is built to be reused by every
future asynchronous need (AI generation, notifications, scheduled
maintenance) without architectural change. Full reasoning in
`docs/adr/0009-background-job-platform.md`; this entry is the what.

**Two named seams, both closed this milestone.** Milestone 10's own
ADR predicted content sync's synchronous-to-async conversion as a
"worker calling it instead of a controller calling it inline"
migration — that's exactly what happened, unchanged from the
prediction. Milestone 10.1 left System Health's `backgroundQueue`
hardcoded specifically because no real queue existed to report on —
now it does, and the metric is real.

**`SyncWordPressPostsJob`: `ContentSyncController::sync()` dispatches,
doesn't block.** The endpoint now authorizes, fast-fails on a missing
credential (still a synchronous `422` — that's a cheap check, not
"expensive synchronization"), sets `Site.status = Syncing`, dispatches
the job, and returns `202 Accepted` immediately. `ContentSyncService::sync()`
itself is otherwise unchanged — the same DTO, the same idempotency
logic, the same failure handling — only who calls it, and when,
changed.

**The `Syncing` transition lives in two places, deliberately.** The
controller sets it before dispatch, for instant feedback (a queued job
might not run for a moment). `ContentSyncService::sync()` *also* sets
it at the start of its own execution, so a retried attempt after a
transient failure re-enters "in progress" cleanly instead of showing a
stale `Error` badge between attempts. Two writes to the same field,
each covering a real gap the other can't see.

**A second job proves the pattern generalizes, used by a new
Scheduler task, not force-fit into an existing button.**
`RefreshSiteMetadataJob` shares the same retry/backoff/uniqueness
shape as the sync job. It's consumed by a new daily
`Schedule::call()` task (`routes/console.php`) that refreshes metadata
for every connected site — the exact "periodic background
re-verification" scenario Milestone 9's own ADR predicted. Deliberately
*not* wired into the existing manual "Refresh Metadata" button, which
stays synchronous: that action is a single, bounded request, and a
user clicking it wants an answer in seconds, not a queue round-trip.

**Real retry, backoff, and uniqueness — not just configured, verified.**
Both jobs: 3 tries, `[10, 30, 60]`s backoff, and per-site uniqueness
via Laravel's cache-lock mechanism. The uniqueness guarantee was
tested against the real `database` driver (not `Queue::fake()`, which
bypasses locking entirely) — dispatching the same site's sync job
twice in a row genuinely produces one row in the `jobs` table, not
two.

**A verified, not assumed, credential-security property.** Traced
`SerializesModels`' actual behavior rather than trusting framework
convention: a job's model properties serialize as a class name plus
primary key only, refetched fresh on execution. `Site.credential` is
never eager-loaded onto a job's `Site` property, so the encrypted
WordPress Application Password never touches the `jobs` table's
payload column, at any point, verifiably.

**System Health's queue metrics are real.** A new `QueueHealthChecker`
(mirroring the existing `DatabaseHealthChecker`) reads real
`pending`/`failed` counts and the configured driver straight from the
`jobs`/`failed_jobs` tables; `queueStatus` derives to `degraded` the
moment a real failed job exists. Verified live: a real sync dispatched
against the seeded environment's genuinely unreachable domain, picked
up by a real `queue:work` process, correctly failed and landed in
`failed_jobs` — and `GET /system-health` reflected `1 failed` /
`degraded` immediately after, in a real browser, not a mocked
assertion.

**Frontend: conditional polling, not a spinner or WebSockets.**
`useSite`/`useSyncStatus` poll every 2 seconds only while the
underlying resource's status is `syncing`, stopping automatically the
moment it settles. `SyncButton` no longer shows created/updated/
skipped counts — that synchronous response shape doesn't exist
anymore — it shows "Sync queued," then the site's existing status
badge and `SyncSummary` card carry the live progress via the polling
hooks. `SiteDetail` watches for the status transition *away from*
`syncing` and invalidates the posts query at exactly that moment — the
one piece of manual cache coordination polling alone doesn't cover.
The polling logic is isolated to exactly two hooks, deliberately, so a
future real-time push mechanism replaces it without touching any
component or backend contract.

**Verified end-to-end with a real queue worker, not just `Queue::fake()`.**
Backend: 103 Pest tests passing (up from 95), including dispatch
assertions, real uniqueness-locking behavior, real queue-metrics
correctness, and a Scheduler-registration check. Frontend: typecheck/
lint/build all pass. Live browser verification ran an actual
`php artisan queue:work` process alongside the app servers — clicked
"Sync Content," watched the job get picked up and processed by a real
worker, watched the site correctly land in `Connection Error` against
the seeded environment's fake domain, and watched System Health's
queue metrics update to match. A live `axe-core` pass against the site
detail page (before and after a sync) and the dashboard returned zero
violations throughout.

---

## 2026-07-14 — Milestone 10.1: API Completion & Frontend Migration

The last mock data left the frontend — deliberately, one widget at a
time, not as a uniform find-and-replace. `src/services/mock/` no
longer exists. This milestone is both a feature milestone (four new
real backend domains) and a technical-debt milestone (a Future
Backlog item flagged across three prior reviews, closed).

**Audited six widgets individually, not migrated uniformly.** The
real question per widget was never "swap the data source" — it was
"does real data already exist," "can it be derived from data that
already exists without a new table," or "is there honestly nothing
real to migrate to yet." Four different answers, four different
outcomes: Analytics Preview and System Health had real underlying
data ready to aggregate; Recent Activity had no persisted event log,
so it's derived live instead of newly logged; Recent Drafts reused an
existing model scope; Quick Actions turned out to be two actions with
real destinations and two without, wearing one component; AI
Assistant Preview had nothing real to migrate to and stays mocked,
unchanged since Milestone 5/7's own deferral.

**Recent Activity, derived from three real queries, not a new
table.** `DashboardService::recentActivity()` composes recently
published posts, recently created drafts, and recently connected
sites — straight from `Post.published_at`/`created_at` and
`Site.last_connected_at`, columns that already exist and are already
authoritative — merges the three result sets, sorts by recency, and
returns the top N. No activity-log table was built; those timestamps
already say everything the feed needs. Accepted, named trade-off:
three queries per request instead of one, fine at today's real usage
(see Engineering Journal).

**Analytics Preview reuses the exact table the Dashboard trend
calculation already uses.** `AnalyticsSnapshot` (built Milestone 7 for
the Dashboard summary's period-over-period trend) now also backs a
second, independent chart with a different time-range shape — the
same historical data, aggregated two different ways for two different
widgets, not a second data source built to match the mock's old shape.

**System Health: three real signals, one honest placeholder.**
`apiStatus` and `wordpressConnection` are real (a shared
`DatabaseHealthChecker`, extracted from `HealthController` to close a
duplication finding rather than copy its check inline, and real
`Site.status` values across the workspace). `storageUsedPercent` is a
real aggregate of `Site.storage_used_mb`/`storage_limit_mb`.
`backgroundQueue` stays hardcoded (`0` pending, `operational`) —
deliberately, since no real queue exists until Milestone 11, and
simulating a metric for a system that doesn't exist yet would be
dishonest, not "more complete."

**Recent Drafts: one new accepted query value, not a new endpoint.**
`IndexPostsRequest` now accepts `status=unpublished` alongside the
real `PostStatus` enum values; `PostController::index()` branches to
the already-existing `Post::scopeUnpublished()` scope. Same endpoint,
same Policy, same tenant-isolation guarantees the existing tests
already proved — no parallel "recent drafts" route. `PostResource`
gained `site_name` (eager-loaded everywhere `Post` is returned, no new
N+1) so the widget never needs a second request to know which site a
draft belongs to.

**Settings: real data, deliberately not editable.** `GET /settings`
returns genuine workspace name/slug/member count and user name/email/
role instead of "not yet implemented" — but there's no form and no
`PATCH` endpoint. Building an editable-preferences feature now would
mean guessing what a user should be able to change, with no product
decision behind it yet — the same deferred-scope discipline already
applied to Registration (Milestone 8) and the "AI Jobs" table
(Milestone 7).

**Quick Actions, honestly split.** "Connect WordPress Site" and "View
Analytics" now navigate to `/wordpress` and `/analytics` respectively
— real destinations that already exist. "New Post" and "Generate AI
Draft" stay genuinely `disabled` — no post-creation UI and no AI
backend exist yet, so there's nothing real to point them at.
`mockQuickActions` moved out of the (now-deleted) `services/mock/`
into the component itself, since it was always static UI
configuration, not simulated API data — it never belonged under a
"mock service" label in the first place.

**Pagination reviewed again, deliberately deferred again.** A named
Future Backlog item since Milestone 7. Reviewed as part of this
milestone's own technical-debt sweep and left open — it still needs
its own real page-size/UI decision, and this milestone's actual
objective (mock-to-real migration) didn't depend on it. Named
explicitly in the report, not silently dropped a second time.

**Validation.** Backend: 95 Pest tests passing (up from 83), 12 new,
covering every new endpoint plus the `unpublished` status filter and
`site_name` field. Frontend: typecheck/lint/build all pass. A live
`axe-core` pass against `/dashboard` and `/settings` — the two pages
carrying entirely new real-data content — returned zero violations on
both. Verified live in a production-build browser: every widget
renders real data with no console errors, both real Quick Actions
links navigate correctly, and Settings shows the seeded workspace's
actual name/slug/member count and the logged-in user's real name/
email/role.

---

## 2026-07-14 — Milestone 10: Content Synchronization Platform

The platform reads real content back from a connected WordPress site
for the first time — previously it only ever wrote connection
metadata (Milestone 9). A generic sync engine, one concrete content
type (Posts), and the first UI `Post` (built Milestone 7) has ever
had. Full reasoning in `docs/adr/0008-content-synchronization.md`;
this entry is the what.

**Redefined mid-roadmap, by explicit brief.** `docs/ROADMAP.md`'s
Milestone 10 slot previously read "API Completion & Frontend
Migration." This milestone's brief explicitly redefined it as Content
Synchronization instead — the displaced original scope is preserved,
not dropped, as the new Milestone 10.1.

**A pre-existing collision this milestone had to resolve first.**
`Post`/`PostController` have existed since Milestone 7 — full CRUD, a
Policy, a Resource — but never had a frontend consumer. The real
architecture question wasn't "how do we model synced content" in
isolation, it was whether a WordPress-synced post belongs in the same
table as one a user types into WP Studio directly. Decided yes:
`posts` gained nullable sync-tracking columns rather than a parallel
table, so every existing and future `Post` consumer treats both
origins as the same domain concept.

**A generic engine, not a generic schema.** The brief required this
layer to generalize to future Pages/Media/Categories/Tags without
hardcoding "Posts." Built the genericity into the *engine*
(`ContentSyncService` knows nothing about "posts," only about a small
`ContentTypeMapper` contract it's handed) rather than a polymorphic
content table — the identical trap `docs/adr/0005-domain-model.md`
already named and avoided for the "AI Jobs" table: guessing a
one-size-fits-all schema before a second real content type exists to
validate it against would likely mean a breaking migration the moment
Pages or Media actually gets built. `WordPressPostMapper` is the only
implementation today; a future Pages sync is a new mapper, zero
changes to the orchestrator.

**Idempotent by content hash, not a timestamp heuristic.** Every sync
run computes a sha256 hash of each item's mapped, change-relevant
fields and compares it against the stored `sync_hash` before writing —
unchanged content is skipped entirely, not assumed unchanged from a
single external timestamp field alone. A unique
`(site_id, wordpress_post_id)` index is the actual duplicate-import
guard (SQL unique indexes don't constrain `NULL` against `NULL`, so
manually-created posts are unaffected). Verified directly: re-syncing
identical fixture data twice produces zero new rows on the second
call; changing one field produces exactly one update.

**Reused the existing `Post` read surface instead of duplicating it.**
The brief's own example route list included a nested
`GET /sites/{site}/posts`. `PostController::index` (Milestone 7)
already scopes to the current workspace's sites and already accepts a
`site_id` filter — a nested alias would have duplicated that query for
a cosmetic URL difference. Only two genuinely new routes:
`POST /sites/{site}/sync` and `GET /sites/{site}/sync-status`.

**`WordPressClientContract` gained a second method, deliberately.**
`fetchCollection()` reuses `HttpWordPressClient`'s existing private
request/retry/timeout/auth machinery (extracted a shared
`assertSuccessfulJsonArray()` helper so `fetchRequired()` and
`fetchCollection()` don't duplicate response-validation logic).
Milestone 9 kept the contract to one method deliberately ("verify" and
"refresh" were the same operation wearing two names) — fetching a
single site's metadata and fetching a paginated content collection are
genuinely different operations, so a second method here doesn't
violate that precedent, it's the same discipline applied correctly to
a case that actually needs two.

**Failure handling reuses Milestone 9's pattern exactly.** A total
failure to reach WordPress at all marks the site `Error` with a stored
`connection_error` — the identical mechanism
`SiteConnectionService::syncFromWordPress()` already uses for verify/
refresh failures, not a parallel one. A single item failing to map
(malformed WordPress JSON) is recorded in the sync result and the
batch continues, without touching `Site.status` — the connection
itself is fine even if one item wasn't.

**`SiteStatus::Syncing`, unused since Milestone 6/7, stays unused.**
Flagged during this milestone's own architecture review as a signal
sync had been anticipated. A synchronous, single-request sync doesn't
have a meaningful window to report "in progress" to a second observer
— the request that triggered it is the only thing waiting. Left in
place as the value a future queued sync (Milestone 11) will actually
set.

**Frontend.** `/wordpress/[id]/posts` and `/wordpress/[id]/posts/[postId]`
— this app's second level of route nesting, following the pattern
`/wordpress/[id]` established in Milestone 9. Four new components
(`PostsTable`, `PostDetail`, `SyncButton`, `SyncSummary`) compose only
existing primitives — no new UI primitive needed. `SyncButton` and
`SyncSummary` coordinate entirely through TanStack Query cache
invalidation (`useSyncSite`'s `onSettled` invalidates the sites list,
the site's posts, and its sync-status), the same mechanism
`useDisconnectSite`/`useVerifyConnection` already use.

**Verified end-to-end against a real graceful-failure path, live in a
browser.** No real WordPress server exists in this environment (same
constraint noted since Milestone 9), so the success path is covered by
9 dedicated Pest tests mocking `Http::fake()` (create, idempotent
re-sync, update detection, trash-skipping, mapper correctness,
credential-required, authorization, workspace isolation, sync-status).
The failure path was driven live in a production-build browser against
the seeded `Acme Blog` site's fake `.example.com` domain: clicking
"Sync Content" produced a real `WordPressConnectionException`, flipped
the site to `Connection Error` with a stored reason, and rendered that
error in both the sync button's own inline message and the existing
site-level error banner — the same display path Milestone 9's verify/
refresh failures already use.

---

## 2026-07-14 — Milestone 9: WordPress Integration Platform

Real WordPress connections — the platform now actually talks to
external WordPress sites, not just its own database. A dedicated
`App\Services\WordPress\` integration layer, Application Password
authentication, an SSRF guard, encrypted credential storage, and the
first real frontend site-management UI. Full reasoning in
`docs/adr/0007-wordpress-integration-architecture.md`; this entry is
the what.

**Architecture review before implementation, as required.** Confirmed
every existing platform service (`CurrentWorkspaceResolver`,
`ApiResponse`/`ApiExceptionHandler`, `SitePolicy`, auth middleware) was
directly reusable — the only genuinely new piece is the HTTP client
that talks to an external, uncontrolled system. Reviewed and approved
before any code was written; see the milestone's own approval thread.

**A dedicated integration layer, not logic scattered across
controllers.** `App\Services\WordPress\Contracts\WordPressClientContract`
(one method — verify and refresh are the same fetch over the wire) →
`Client\HttpWordPressClient` (the only class that ever makes an HTTP
request to WordPress) → `Authentication\ApplicationPasswordAuthenticator`
(HTTP Basic Auth, isolated so a future second auth method is additive)
→ `DTO\WordPressSiteInfo` → `Exceptions\{WordPressConnectionException,
WordPressAuthenticationException,WordPressResponseException}` (all
extending the existing `ApiException`, rendering through the unchanged
`ApiExceptionHandler`) → `Security\UrlSafetyValidator` (the SSRF
guard). `SiteConnectionService` orchestrates all of it — deliberately
not an extension of `SiteService` (see the ADR's Service Boundaries
section for why that's a real distinction, not just a naming choice).

**Two REST calls load-bearing, three best-effort.** The root index
(proves it's WordPress) and `/wp/v2/settings` (proves the credential
works) propagate failure; `/wp/v2/themes`, `/wp/v2/plugins`,
`/wp/v2/users` are each independently capability-gated by WordPress
itself and resolve to `null` on failure rather than failing the whole
connection — a real, working implementation of "graceful degradation,"
not an aspiration. `userCount` comes from the `X-WP-Total` response
*header* on a `per_page=1` request, not the body — WordPress's own
pagination convention, avoided fetching every user just to count them.

**An honest accounting of what's detectable.** `wordpress_version` and
`php_version` are real columns, always `null` — stock WordPress
doesn't expose either through its public REST API without a companion
plugin. Documented as a real constraint in the ADR, not silently
guessed at — the same discipline `0005-domain-model.md` applied to the
"AI Jobs" table.

**Schema.** A new migration (not another amendment of the M6/M7 `sites`
migration — a deliberate divergence from that precedent, reasoned
through in the ADR) adds `url`, `php_version`, `plugin_count`,
`user_count`, `timezone`, `language`, `last_connected_at`,
`last_checked_at`, `connection_error`. A new `site_credentials` table
— separate from `sites` specifically so `SiteResource` can never
accidentally serialize a credential — stores `wp_username` plain and
`application_password` via Eloquent's `encrypted` cast. New
`SiteStatus::Error` case, distinct from `Disconnected`.

**Security — SSRF was the first-order risk, not a side concern.**
"Connect to a URL a workspace member supplies" is a request-forgery
primitive without a check. `UrlSafetyValidator` rejects non-http(s)
schemes, local hostnames, and private/reserved literal IP addresses
before any request is sent — verified in tests via
`Http::assertNothingSent()`. A new `wordpress-connection` rate limiter
(10/minute per user) covers `connect`/`verify`/`refresh-metadata`.
Named, accepted residual risk: no DNS resolution check (a hostname
that resolves to a private IP isn't caught) — deliberate, to keep the
guard network-free and deterministic in tests; deferred to Milestone
19 alongside the cross-domain cookie decision.

**`POST /sites` changed contract, deliberately (flagged in the
architecture review before implementing).** Now requires `url`,
`wp_username`, `application_password`; every WordPress-derived field
a client could previously set directly (`wordpress_version`, `theme`,
`plugin_updates_available`) is now server-only, sourced from the real
handshake. `UpdateSiteRequest` no longer accepts `status` either —
status transitions now only happen through the real lifecycle actions,
closing the exact integrity gap ("claim connected without verifying")
this milestone exists to fix.

**Real CRUD, extended.** `SiteController` gained `disconnect` (deletes
the stored credential, not just a status flip — "store only what's
required"), `verifyConnection`, `refreshMetadata`. `disconnect`
requires owner/admin (matching `update`); verify/refresh require only
membership — read-adjacent, the same posture `SitePolicy::view()`
already takes.

**Frontend.** New `dialog.tsx` primitive (hand-extracted via
`--view`, not `shadcn add`, to protect the hardened `Button` — same
process as Milestone 4's `sidebar`/`sheet`; fixed the same
accessible-name gap in the generated close button while integrating).
`src/features/wordpress/` — a Connect Site dialog (React Hook Form +
Zod), a sites grid with live status badges, and `/wordpress/[id]` —
this app's first dynamic/nested route, which also closed the
`AppSidebar` `isActive` exact-match gap deferred since Milestone 4.1
(now matches a full path segment prefix, not a bare `startsWith`).

**Testing.** 16 new Pest tests (`WordPressConnectionTest`) — successful
connection, rejected credentials, unreachable host, malformed
response, partial-capability graceful degradation, SSRF rejection
(asserting zero HTTP calls were made), disconnect/verify/refresh
lifecycle, tenant isolation, rate limiting — all against `Http::fake()`,
never a live WordPress server. Existing Site tests updated for the new
`StoreSiteRequest`/`UpdateSiteRequest` contracts. 73/73 passing (up
from 57). Found and fixed a real bug while writing them: Laravel's
`Http::retry()` defaults to throwing on any non-2xx response once
retries exhaust, which was silently pre-empting this integration's own
401/403 → `WordPressAuthenticationException` mapping — fixed with an
explicit `throw: false`. Also found `SiteCredential` was missing
`HasFactory`, caught immediately by the first factory-based test.

**Verification.** `typecheck`, `lint`, `build` all pass — `/wordpress`
and `/wordpress/[id]` both new routes. Backend `php artisan test`:
73/73. Full flow driven in a real, production-mode browser with real
internet access (not mocked): login → WordPress page → nested site
detail → sidebar prefix-match highlighting → Connect Site dialog
client-side validation → a **real** SSRF rejection against
`192.168.1.1` (zero mocking, the actual `UrlSafetyValidator` running
against a real request) → a **real** rejection connecting to
`example.com` (a genuine reachable, non-WordPress site) — 8/8 checks
passed, zero unexpected console errors.

**Documentation** — `docs/adr/0007-wordpress-integration-architecture.md`
(new); `docs/PROJECT.md` gained a "WordPress Integration Platform"
section and updated Known Limitations/Stack table/Status;
`docs/ROADMAP.md` milestone 9 marked complete (swapped with the
originally-planned "API Completion & Frontend Migration," now
milestone 10 — WordPress Integration was the more natural next step
after Authentication, and swapping positions kept the release's total
milestone count unchanged); `docs/ENGINEERING_JOURNAL.md` gained
investigation entries, resolved Future Backlog items, and new ones.

## 2026-07-13 — Milestone 8: Authentication & Authorization

Real login, at last — Laravel Sanctum cookie/session SPA auth, wired
into every route Milestones 6–7 left open, plus a new Current
Workspace Resolver architecture that closes two real cross-tenant
vulnerabilities the pre-implementation architecture review surfaced.
Full reasoning in `docs/adr/0006-authentication-architecture.md`; this
entry is the what.

**Architecture review before implementation, as required.** Reading
the existing backend surface (`config/cors.php`, `config/session.php`,
`SitePolicy`/`PostPolicy`, every controller) surfaced two concrete,
previously-undocumented vulnerabilities, not hypothetical risks:
`IndexSitesRequest`/`IndexPostsRequest` validated `workspace_id`/
`site_id` as "any existing ID," no membership check; `DashboardService::summary()`
had zero workspace scoping at all — both invisible with one seeded
workspace, both real leaks the moment a second exists. The initial
proposal (explicit, policy-checked `workspace_id` per request) was
revised on explicit direction into a centralized **Current Workspace
Resolver** — see the ADR's Context and Alternatives sections.

**Backend — Sanctum + Current Workspace Resolver.**
`composer require laravel/sanctum`, `$middleware->statefulApi()` in
`bootstrap/app.php`. `App\Services\CurrentWorkspaceResolver` (the
resolution strategy, isolated in one class) → `App\Http\Middleware\ResolveCurrentWorkspace`
(runs after `auth:sanctum`, resolves once per request) →
`App\Support\CurrentWorkspaceContext` (a `scoped()` container binding —
one instance per request, Octane-safe even though nothing runs Octane
today). `SiteController`/`PostController`/`DashboardController` all
depend on the context via constructor injection instead of trusting a
client-supplied ID. `index()` actions need no per-row authorization —
membership in the resolved workspace is already guaranteed, which is
also how this milestone resolves the N+1 risk Milestone 7's Future
Backlog flagged, architecturally rather than via eager loading.

**Auth endpoints.** `Http/Controllers/Api/V1/Auth/AuthController`
(`login`, `logout`) + `UserController` (`show`, the profile endpoint).
`login()` regenerates the session ID (session-fixation mitigation);
`logout()` invalidates the session and rotates the CSRF token. A new
`InvalidCredentialsException` (401, code `INVALID_CREDENTIALS`) is
distinct from plain `AuthenticationException` (401, `UNAUTHENTICATED`)
— a wrong password and a nonexistent email return identically (can't
probe which emails are registered), while the frontend can still tell
"this login attempt was wrong" from "your session expired" and show
the right copy for each. `RateLimiter::for('login', ...)` — 5/minute,
keyed by `email|ip` together, matching Laravel Fortify's own default.

**Real CRUD is now actually authorized.** `$this->authorize()` calls
added to `SiteController`/`PostController` using the Policies Milestone
7 wrote and tested — no policy logic changed, only wired in.

**Registration deliberately deferred.** Raises "which workspace does a
new user land in" — a real product decision `docs/adr/0005-domain-model.md`
already deferred once (workspace creation as "a future onboarding-flow
concern"). Login is against `DemoDataSeeder`'s existing seeded user
(`test@example.com`) until a future onboarding milestone designs
registration for real, rather than guessing at auto-workspace-creation
now. See the ADR's Future IAM Roadmap for what's next when it does.

**Frontend.** `src/lib/api-client.ts` — `credentials: "include"` on
every request, a centralized CSRF-cookie handshake (deduplicated
against concurrent callers) before any mutating call, and a
`UNAUTHORIZED_EVENT` fired only on a genuine `UNAUTHENTICATED` 401 (not
`INVALID_CREDENTIALS`, which would misfire on the login page itself).
`src/features/authentication/` — `useCurrentUser()` (TanStack Query,
**not** a Zustand store — same client/server-state split
`docs/adr/0003-dashboard-data-architecture.md` already established for
everything else) is the single source of truth for "who is logged in";
a 401 is caught and resolved to `null` inside `queryFn`, not left to
throw, since "nobody is logged in" is an expected result, not a query
error. `ProtectedLayout` is real now — loading state while the session
check is in flight, redirect to `/login?redirect=<path>` on no session
(intended destination preserved, `/dashboard` itself omitted since it's
the default landing page anyway). New `(auth)` route group (`/login`),
its own minimal layout, mirroring `(app)`. `AppHeader`'s user menu
(previously disabled placeholders) shows the real signed-in user and
has a working "Sign out."

**Testing.** 19 new Pest tests (`AuthenticationTest` — login/logout/
profile/rate-limiting/CSRF; `WorkspaceIsolationTest` — cross-tenant
isolation, the exact vulnerabilities this milestone fixed, verified
closed) on top of Milestone 7's 38, all updated for the new auth
requirement (`SiteCrudTest`/`PostCrudTest`/`DashboardSummaryTest`/
`PolicyTest`/`CrudValidationTest` now authenticate as a workspace
member instead of hitting open routes) — 57/57 passing. Also fixed,
found during test-writing: `ApiExceptionHandler` never actually mapped
`AuthorizationException` to the `FORBIDDEN` envelope — Laravel's own
exception handling converts it to `AccessDeniedHttpException` before
this project's `render()` closure ever sees it, a real (if
previously-unobserved, since nothing threw an authorization exception
before this milestone) gap now closed.

**Verification.** `typecheck`, `lint`, `build` all pass. Backend
`php artisan test`: 57/57. Full flow driven in a real, production-mode
browser (dev-mode Fast Refresh was interfering with observing
client-side navigation during testing — not a real bug, see the
Engineering Journal): unauthenticated redirect with preserved
destination, wrong-password error, successful login landing on the
originally-intended page, real dashboard data rendering, session
surviving a full reload, the user menu showing the real email, sign-out,
and re-protection after sign-out — all verified end-to-end, zero
console errors.

**Documentation** — `docs/adr/0006-authentication-architecture.md`
(new); `docs/PROJECT.md` gained an "Authentication & Authorization"
section and updated Known Limitations/Stack table/Status;
`docs/ROADMAP.md` milestone 8 marked complete; `docs/ENGINEERING_JOURNAL.md`
gained investigation entries and an updated Future Backlog.

## 2026-07-13 — Post-M7 Engineering Review & Platform Modernization

Not a numbered milestone — a review/process session between Milestone
7 and Milestone 8, per that session's own brief. No application code
changed; frontend `typecheck`/`lint`/`build` re-verified clean
afterward as proof.

**Engineering review.** Read `docs/PROJECT.md`, `docs/ROADMAP.md`,
`docs/CODING_STANDARDS.md`, `docs/ENGINEERING_JOURNAL.md`,
`docs/DEVLOG.md`, both new ADRs, and the current repo structure before
changing anything. Found the project's documentation and self-review
discipline to be a genuine strength (ADRs with rejected alternatives,
a living Future Backlog, milestone-report-then-hardening-milestone
loop). Found one real, previously-undocumented risk: **Milestones 6
and 7 — the entire `backend/` app, the frontend API-integration layer,
both new ADRs — had never been committed to git.** `git log` stopped at
"M3–M5." Flagged in the new `docs/SESSION_HANDOFF.md`, then committed
as its own milestone-scoped commit separate from this session's own
documentation work, once confirmed only M6/M7 files were involved.

**Platform modernization — evaluated against what already exists,
added only what filled a real gap.** `docs/PROJECT.md` (architecture),
`docs/DEVLOG.md` (changelog), and `docs/ENGINEERING_JOURNAL.md`'s
Future Backlog (technical debt) already each own one responsibility
well — deliberately did **not** add a redundant `ARCHITECTURE.md`,
`RELEASE_NOTES.md`, or `ENGINEERING_DEBT.md` (reasoning recorded in the
new `docs/AI_ENGINEERING_CONTEXT.md`'s doc map, so it doesn't get
re-litigated next session). Added exactly three new files, each
covering a gap nothing else filled:
- `docs/AI_ENGINEERING_CONTEXT.md` — the onboarding front door this
  session's own brief had to reconstruct by hand (reading order,
  doc-responsibility map, standing environment gotchas). Every future
  session should start here instead.
- `docs/SESSION_HANDOFF.md` — ephemeral, overwritten every session;
  the "where do I resume" doc that was genuinely missing, made
  concrete immediately by using it to flag the uncommitted M6/M7 work
  above.
- `docs/prompts/milestone-lifecycle.md` — the twelve-stage process
  (Architecture Review → ... → Next Milestone) and the production-layer
  checklist this session's own brief specified, saved as a repository
  artifact so it governs Milestone 8 onward without being re-typed.

Also replaced the root `README.md`, still Create Next App's default
boilerplate since Milestone 1 (a Low Priority item named in the
Engineering Journal's Future Backlog, closed here) — now points to
`docs/AI_ENGINEERING_CONTEXT.md`, `docs/PROJECT.md`, and
`backend/README.md` instead of Next.js's own generic getting-started
text.

**Roadmap refined through Milestone 20.** `docs/ROADMAP.md`'s
Milestones 1–7 kept verbatim (historical, already accurate), regrouped
under a new "Release v0.7 — Completed Foundation" heading. Milestones
8–15 (originally a flat list ending at "15. Production Release")
expanded and regrouped into four releases — v0.8 (Authentication, API
Completion & Frontend Migration, WordPress Integration), v0.9
(Background Jobs & Queues, Storage & Media, GraphQL, AI-Assisted
Content Generation, Frontend Testing), v0.95 (Performance & Caching,
Observability, CI/CD & Containerization, Cloud Deployment & Security
Hardening), v1.0 (Production Release) — ending at Milestone 20. Every
new milestone description links back to the specific ADR or Future
Backlog item it closes (e.g. Milestone 8 names the exact N+1 fix
Milestone 7's self-review already flagged), rather than restating scope
already recorded elsewhere.

**Validation** — `typecheck`, `lint`, `build` all pass, matching this
session's own claim of zero application-behavior change. No routes,
API contracts, or database schema touched. Backend test suite not
re-run — no backend code changed this session.

**Self-review.** The main residual risk this session leaves behind is
process, not code: `docs/SESSION_HANDOFF.md` now exists specifically to
prevent the M6/M7 commit gap from recurring silently, but it only works
if it's actually kept current — worth checking, not assuming, at the
start of Milestone 8.

## 2026-07-13 — Milestone 7: Domain & Data Platform

Establishes the business domain Milestone 6's backend foundation was
built to eventually carry — a real multi-tenant model, real CRUD, and
a real historical data source for the Dashboard's trend calculation.
Full reasoning in `docs/adr/0005-domain-model.md`; this entry is the
what.

**Domain modeled before any migration was written** (per the
milestone's own instruction): `Workspace` (tenant root) → `Site` →
`Post`/`AnalyticsSnapshot`; `Workspace` ↔ `User` many-to-many via
`workspace_user` (with a `role`: owner/admin/member); `Post` →
`PublishingJob` (placeholder). "Content," "Publishing," and "AI Jobs"
are domain *areas*, not separate tables — the first two are `Post`
and `PublishingJob`; AI Jobs deliberately has no table yet (see
below).

**Migrations** — `workspaces`, `workspace_user` (new); Milestone 6's
`sites`/`posts` migrations amended in place (not layered with `ALTER
TABLE` migrations — neither had ever run outside local development,
so editing them directly produces an honest schema history rather than
a trail of patches for a table that was never actually shipped without
a workspace); `analytics_snapshots`, `publishing_jobs` (new). Every
relationship is a real `foreignId()->constrained()` with
`cascadeOnDelete()`. Soft deletes on `Site`/`Post` only — not
`Workspace` (tenant deletion deserves its own future flow, not a
bolted-on `deleted_at`), not `AnalyticsSnapshot`/`PublishingJob`
(immutable/operational records, not content). UUIDs deferred,
documented why (no external API consumer yet, one database).

**Self-review caught two missing indexes before they shipped** —
`sites.workspace_id` and `workspace_user.user_id` both had foreign key
*constraints* but no index. SQLite, unlike MySQL/InnoDB, doesn't
auto-index a column just because a constraint references it — both
would have silently full-table-scanned on every workspace-scoped
lookup and `$user->workspaces` query. Fixed before merge, documented
in the ADR as a SQLite-specific gotcha worth re-checking on every
future migration.

**Real CRUD** — `Route::apiResource('sites', ...)` and
`apiResource('posts', ...)`, full `index`/`show`/`store`/`update`/
`destroy`, replacing Milestone 6's placeholders. Six new Form Requests
(`Store`/`Update`/`Index` × Sites/Posts) — `workspace_id`/`site_id`
deliberately excluded from `Update` requests (moving a resource
between parents is an ownership-transfer operation, not a plain edit;
verified a client can't smuggle a different `workspace_id` into a
`PUT` body and have it take effect). `SiteResource`/`PostResource`
render through the existing `ApiResponse` envelope — no new response
logic, same pattern Milestone 6 established. `SiteService::create()`
dispatches `SiteConnected` for the first time (defined but never
dispatched in Milestone 6).

**Real authorization logic, deliberately not wired to any route** —
`SitePolicy`/`PostPolicy` now check real workspace membership/role
(owner/admin can create/update/delete; any member can view; only
owner can force-delete/restore) instead of Milestone 6's
deny-by-default placeholder. Not added to any controller's
`authorize()` — every route stays open until Milestone 8 gives a
request an authenticated user. Tested directly against the policy
classes (`PolicyTest.php`, 3 tests) so the logic is proven correct
ahead of being load-bearing.

**Real analytics history** — `AnalyticsSnapshot` (site_id,
snapshot_date, visitors, posts_published, storage_used_mb; unique per
site per day) replaces the single `sites.monthly_visitors` column.
`DashboardService::summary()` now computes a genuine 14-day-vs-prior-
14-day visitor trend (`monthly_visitors_trend`, nullable — `null`
means "no prior data," not "no change"). Closes a gap the Milestone 5
and 6 reviews both flagged (KPI trend permanently omitted). The
frontend's `map-summary-to-kpis.ts` now renders it — verified live:
"+105.8% vs. prior 14 days" on the Monthly Visitors card, sourced from
real seeded snapshot history, zero console errors.

**Seeding** — `SiteSeeder` replaced by `DemoDataSeeder` (the old name
stopped describing what it seeds): one workspace, one user attached as
owner, 3 sites with posts, plus 28 days of gently-trending
`AnalyticsSnapshot` history per site (two full 14-day windows, so the
new trend calculation has a real non-empty baseline on both sides).

**Placeholder for future queues** — `PublishingJob` +
`PublishingService::schedule()` records intent (a `pending` job row)
without processing anything; the method boundary exists so a future
`ProcessPublishingJob` queued job is additive later, not a refactor.

**"AI Jobs" deliberately has no table** — named in the brief as a
domain concept, but unlike `PublishingJob` (a generic, well-understood
"async operation" shape), a real AI job's shape depends on a specific
provider integration that doesn't exist yet. Documented in the ADR
and Future Backlog as an intentional gap, not filled with a guessed
schema likely to need a breaking migration later.

**Second frontend widget migrated** — WordPress Overview now reads
`GET /api/v1/sites?status=connected` (`src/services/api/sites.service.ts`
+ a mapper), same zero-widget-changes pattern as Milestone 6's KPI
Cards. Added a real Empty state ("no connected site") the mock layer's
fixture data never needed. Verified live: real network request, real
site data rendered, zero console errors.

**Testing** — 38 Pest tests across 6 files (up from Milestone 6's 4):
`SiteCrudTest`, `PostCrudTest` (Feature — full HTTP flows, including
soft-delete and cascade-delete behavior), `DomainRelationshipsTest`
(Database/Relationship — every new relationship, the unique-snapshot-
per-day constraint, model scopes), `CrudValidationTest` (Validation —
every Form Request's rules, asserted against this API's actual
`error.details` envelope shape, not Laravel's default), `PolicyTest`
(Policy — role-based authorization logic in isolation),
`DashboardSummaryTest` (rewritten for the new schema, plus a new case
proving the trend calculation against known snapshot data).

**Process notes**
- Hit a real Laravel gotcha: `belongsToMany()`'s default pivot-table
  name is alphabetical (`user_workspace`), not `workspace_user` as
  named — fixed by passing the table name explicitly rather than
  renaming the migration to match the convention.
- Caught and fixed an off-by-one date range in the trend test's own
  fixture data (not the service) after the first run produced a
  plausible-but-wrong number — see `docs/ENGINEERING_JOURNAL.md` for
  the full investigation.

**Verification** — Frontend: `lint`, `typecheck`, `build` all pass.
Backend: `php artisan test` (38/38 passing), `php artisan route:list`
(15 routes, including the 10 new CRUD routes), `php artisan
migrate:fresh --seed` (clean from scratch, twice — once before and
once after the index fixes). Live integration verified in a real
browser: two widgets pulling real data, real visitor trend rendered,
zero console errors.

**Documentation** — `docs/adr/0005-domain-model.md` (new, comprehensive
— entity relationships, every schema trade-off, rejected alternatives,
future evolution); `docs/ENGINEERING_JOURNAL.md` gained two new
investigation entries, an updated Future Backlog, and two new
**permanent** sections this milestone's brief specifically asked for —
"Interview Highlights" and "Resume Highlights" — both restructured to
accumulate per-milestone subsections going forward (Milestone 4.1's
existing Interview Highlights content became the first subsection
rather than being replaced); `docs/PROJECT.md` gained a "Domain & Data
Platform" section and updated Known Limitations/Status/Stack table;
`docs/ROADMAP.md` milestone 7 (left as "TBD" in Milestone 6) filled in
and marked complete.

## 2026-07-13 — Milestone 6: Backend Foundation (Laravel)

First real backend. Two parts: closing the four verified M5 findings
(quick, scoped fixes — see the Engineering Journal for each), then a
Laravel 12 API foundation with exactly one real endpoint and one
migrated frontend widget, proving the mock-to-real pattern without
rewriting anything the mock layer already got right. Full reasoning in
`docs/adr/0004-backend-foundation.md`; this entry is the what.

**M5 findings closed** (see `docs/ENGINEERING_JOURNAL.md` for the full
reasoning behind each): `WelcomeSection`'s `<h1>` now always renders
(verified 0→1 in the actual production static HTML); the "My
Workspace" placeholder button uses native `disabled`, matching
`QuickActions`; the dead `getQuickActions()` export was removed;
`useNotificationStore` is now wired to `RecentActivity`'s real query
data instead of sitting permanently at zero.

**Environment setup.** Neither PHP nor Composer were on `PATH` in this
environment — found PHP 8.2.12 already installed via XAMPP
(`C:\xampp\php`), installed Composer 2.10.2 via the official installer
script (the `composer.github.io` signature-check endpoint was
unreachable from this network; proceeded without it since the
installer itself came from the canonical `getcomposer.org` domain over
HTTPS), and added thin `php`/`composer` wrapper scripts to
`~/bin` (already on `PATH`) for the rest of the session.

**Laravel scaffold** — `composer create-project laravel/laravel:^12.0
backend`, SQLite for local dev (zero services to run locally),
migrated/seeded automatically as part of the create-project post-install
hooks.

**Architecture** (`backend/app/`): `Http/Controllers/Api/V1/` (one
controller per domain), `Http/Resources/V1/`, `Http/Support/ApiResponse.php`
(the JSON envelope every response goes through), `Http/Middleware/`
(`AssignRequestId`, `SecureHeaders`), `Services/` (`DashboardService`,
the one real business-logic class), `DTOs/` (`DashboardSummaryData`),
`Enums/` (`SiteStatus`, `PostStatus`), `Exceptions/` (`ApiException`
base, `ServiceUnavailableException` example, `ApiExceptionHandler`
centralizing every error response), `Policies/` (`SitePolicy`,
deny-by-default placeholder), `Events/`+`Listeners/`
(`SiteConnected`/`LogSiteConnected`, placeholder, not dispatched yet).
No repository layer — evaluated and deliberately not built (see the
ADR's reasoning: nothing to abstract yet).

**API** — versioned from the start (`routes/api.php` composes
versions, `routes/api_v1.php` holds v1). Seven routes: `GET
/api/v1/health` (real database check, separate from Laravel's built-in
`/up`), `GET /api/v1/dashboard/summary` (real), and five placeholders
(`sites`, `posts`, `analytics`, `ai`, `settings`) — one per domain the
brief named, each returning a valid 200 with empty/minimal data rather
than a 501, so the frontend can integrate against the route shape
immediately even before the real logic exists.

**Database** — two foundational tables, `sites` and `posts`
(migrations + factories + `SiteSeeder`, fixture data shaped to
resemble the frontend's own mock fixtures for a plausible side-by-side
comparison). `DashboardService::summary()` aggregates connected sites,
published/draft post counts, and storage/visitor sums — verified
correct via 4 Pest Feature tests, including that a disconnected site's
data is correctly excluded from the aggregate.

**Error handling** — `ApiExceptionHandler::register()` (called from
`bootstrap/app.php`'s `withExceptions()`) maps every failure mode —
validation, not-found, unauthenticated, rate-limited, app-thrown
`ApiException`, or an unhandled `Throwable` — through the same
`ApiResponse::error()` envelope, with production responses never
leaking raw exception messages (`config('app.debug')`-gated).

**Observability/security groundwork, no external services wired
yet** — `AssignRequestId` middleware (generates or forwards
`X-Request-Id`, pushes it into Laravel's log context, echoes it on the
response and in error envelopes); `SecureHeaders` middleware
(`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`); `config/cors.php` restricted to the frontend's
own origin via `FRONTEND_URLS` (not the framework's wildcard default),
`supports_credentials: true` in anticipation of Milestone 8's Sanctum
SPA auth. Sentry/OpenTelemetry: documented `.env.example` placeholders,
not implemented.

**Testing** — Pest 3 installed via `composer require` (not `laravel
new --pest`, since the project already existed); `tests/Pest.php`
binds `RefreshDatabase` for Feature tests. `DashboardSummaryTest.php`:
envelope shape, correct aggregation against seeded data, the
disconnected-site-excluded case, the zero-data case, and the
request-ID header — 4 tests, 15 assertions, all passing. Deliberately
not full coverage, per the brief's own scope.

**Frontend integration** — `src/lib/api-client.ts` (the one place that
calls the real API and unwraps its envelope, throwing a typed
`ApiError` on failure), `src/services/api/dashboard.service.ts`
(`getDashboardSummary()`), `src/features/dashboard/utils/map-summary-to-kpis.ts`
(adapts the API's raw numeric response into the exact `Kpi[]` shape
`KpiCards` already consumed). `kpi-cards.tsx` itself required **zero**
changes — only `use-kpis.ts` changed which function it calls. The
now-dead `getKpis()`/`mockKpis` were removed from the mock service
layer rather than left unused. Verified end-to-end in a real browser
against the running Laravel server: KPI Cards shows live aggregated
numbers (3 connected sites, 9 published posts, 3 drafts, 18,204
visitors, 8.2 GB / 30 GB storage — all traced back to the seeded
database), zero console errors, confirmed via the actual network
request hitting `localhost:8000`.

**Tooling housekeeping** — `backend/` excluded from the frontend's
ESLint (`eslint.config.mjs`), Prettier (`.prettierignore`), and
TypeScript (`tsconfig.json`) — without this, `npm run lint` recursed
into `backend/vendor/`'s bundled JS (Whoops's error-page assets) and
produced dozens of irrelevant findings.

**Process notes**
- Hit the same class of environment issue documented in Milestone 3's
  entry (`.next` cache corruption via `EINVAL: invalid argument,
  readlink` on this OneDrive-synced project path) a second time, this
  time as `bootstrap/cache` failing Laravel's writability check with a
  stray `DENY` ACL entry on a reparse-point directory. Same fix both
  times: delete and recreate the directory fresh. Two occurrences
  across two different toolchains (Next.js, Laravel) is enough to call
  this a standing environmental hazard of developing on a
  OneDrive-synced path, not a one-off — noted in the Engineering
  Journal as a documented, repeatable fix rather than something to
  rediscover a third time.
- Hit a leftover zombie dev-server process squatting on port 3000
  twice during verification (once for the Next.js dev server, a
  recurrence of the same class of issue the Milestone 5 review
  independently found and documented) — same fix: identify the PID via
  `netstat`, terminate it, restart clean. Worth deliberately checking
  for before starting a dev server in future milestones, not just
  reacting to it when a verification run produces confusing results.

**Verification** — Frontend: `lint`, `typecheck`, `build` all pass.
Backend: `php artisan test` (4/4 passing), `php artisan route:list`
(7 routes registered correctly), `php artisan migrate` (clean, no
errors). Live integration verified in a real browser (Playwright):
real network request to the Laravel API, real seeded data rendered,
zero console errors.

**Documentation** — `docs/adr/0004-backend-foundation.md` (new);
`docs/ENGINEERING_JOURNAL.md` gained the M5-findings-closure entry, a
new investigation entry on the OneDrive reparse-point issue, and an
updated Future Backlog; `docs/PROJECT.md` gained a "Backend Foundation"
section, an updated Stack table, and updated Known Limitations/Status;
`docs/ROADMAP.md` milestone 6 marked complete (retitled from the
original "State Management"/"Laravel REST API" split, both folded into
this one milestone); `backend/README.md` replaces Laravel's default
boilerplate with real local-setup/environment-configuration docs.

## 2026-07-13 — Milestone 5: Dashboard Experience

First milestone with real (mocked) data and real async states. Nine
dashboard widgets on top of the existing Product Shell — no shell code
rewritten, every widget composes existing `common/`/`ui/` primitives.
Full reasoning in `docs/adr/0003-dashboard-data-architecture.md` and
`docs/ENGINEERING_JOURNAL.md`; this entry is the what.

**Data/state infrastructure (built first, before any widget UI)**
- `src/services/mock/dashboard.mock-data.ts` + `dashboard.service.ts`
  — fixture data and `delay()`-wrapped async functions standing in for
  a future Laravel REST API.
- `src/features/dashboard/types/dashboard.types.ts` — plain data
  shapes (`Kpi`, `ActivityItem`, `WordPressOverview`, `AnalyticsPoint`,
  `Draft`, `SystemHealth`, ...), no presentation concerns.
- `src/components/common/query-provider.tsx` — `QueryClient` (created
  per-instance via `useState`, not module scope), `staleTime: 60s`,
  `retry: 2` with exponential backoff, dev-only `ReactQueryDevtools`
  (dynamically imported, `ssr: false`). Wired into the root layout as
  the outermost provider.
- `src/features/dashboard/hooks/use-*.ts` — six `useQuery` wrapper
  hooks, one per widget's data need, query keys under `["dashboard",
  ...]`.
- `src/store/notification-store.ts` — the one Zustand store this
  milestone adds (notification count), wired into the header's
  existing notification `Popover` with an explicit "Mark all as read"
  button (considered clearing on popover-open, rejected — would clear
  the count before the user could read the "you have N updates"
  message).
- `src/components/common/loading-state.tsx` — one new shared
  component (centered spinner + message, `role="status"`); every other
  widget need was covered by existing primitives.
- `src/components/ui/progress.tsx` — added via `npx shadcn add
  progress --dry-run` first (confirmed pure create, zero conflicts),
  then the real run.

**Nine widgets** (`src/features/dashboard/components/`), composed on
the dashboard page (`src/app/(app)/dashboard/page.tsx`) in the brief's
specified order:
- **Welcome Section** — greeting/description/date/workspace-selector
  placeholder. Greeting and date are computed client-side after mount
  (reusing `ThemeToggle`'s existing `mounted`-guard pattern), not
  server-rendered — this route is statically generated, so a
  build-time `Date.now()` would go stale for later visitors. See
  `docs/ENGINEERING_JOURNAL.md`.
- **KPI Cards** — 5 metrics via `useKpis()`, composes the existing
  `StatCard` directly (no new component needed; `Kpi.trend`'s shape was
  designed to match `StatCard`'s prop exactly).
- **Quick Actions** — 4 static placeholder cards, genuinely `disabled`
  (not `aria-disabled` + `preventDefault()` — that two-layer pattern is
  for links with a real destination to guard against; these buttons
  have none). Only new component in this milestone that's a Server
  Component (no data fetching, no interactivity).
- **Recent Activity** — mock timeline via `useRecentActivity()`,
  relative timestamps via a new `formatRelativeTime()` util
  (`src/lib/format.ts`, `Intl.RelativeTimeFormat`).
- **WordPress Overview** — one mock site, `StatusBadge` for connection
  status.
- **Analytics Preview** — Recharts `AreaChart`, local `useState` range
  toggle (7D/30D/90D) — deliberately not Zustand; nothing else on the
  dashboard reacts to it. Chart colors reference the existing
  `--chart-1..5` CSS custom properties directly (already defined in
  `globals.css`, unused until now). Chart wrapped in `role="img"` with
  a computed `aria-label` summarizing the trend range for screen
  readers.
- **Recent Drafts** — the retry/error demonstration widget. The mock
  service fails the first two calls per browser session deterministically
  (module counter, not `Math.random()`), paired with a `retry: 1`
  override (global default is 2) so both automatic attempts are
  guaranteed to exhaust before the Error UI renders. Verified
  end-to-end with Playwright: error visible on load, draft list visible
  after clicking "Try again." See `docs/ENGINEERING_JOURNAL.md`.
- **AI Assistant Preview** — prompt textarea + suggested-prompt chips
  (both interactive — filling the textarea is real UX to preview) +
  `Generate` button, genuinely `disabled` (not silently inert on click)
  since there's no backend yet. Future integration point
  (`POST /api/ai/drafts`-shaped) documented inline.
- **System Health** — service status badges (API, background queue)
  and `Progress` for storage usage.

**Duplication caught during self-review.** `SiteStatus`/`ServiceStatus`
→ badge-color mapping was written identically in both
`wordpress-overview.tsx` and `system-health.tsx`. Extracted to
`src/features/dashboard/utils/status-meta.ts`, imported by both —
reactive extraction (two real call sites triggered it), not a
speculative shared-utils file built ahead of need.

**Verification** — Playwright across four viewports (375/768/1440/
1920px): zero console errors on every breakpoint. `axe-core` (WCAG 2A/
2AA/2.1AA + `best-practice` tags, the widened scope established in
Milestone 4.1): 0 violations. Interaction checks: Analytics range
toggle (`aria-pressed` updates correctly), AI Assistant suggested-prompt
click fills the textarea, notification popover opens/shows empty state,
Recent Drafts error→retry→success flow (separate script, both
assertions passed on the first run). `typecheck`, `lint`, `build` all
pass. Cleaned up all temporary verification scripts and uninstalled
`playwright`/`@axe-core/playwright` afterward, as established practice.

**Documentation** — `docs/adr/0003-dashboard-data-architecture.md`
(new); `docs/ENGINEERING_JOURNAL.md` gained two dated investigation
entries plus a new permanent "Future Backlog" section
(High/Medium/Low/Deferred, rolling up open items from this and prior
milestones); `docs/PROJECT.md` gained a "Dashboard Experience" section
and updated Known Limitations/Status; `docs/ROADMAP.md` milestone 5
marked complete with an updated description, milestone 6 annotated
noting its core infrastructure was already delivered here.

## 2026-07-11 — Milestone 4.1: Product Shell Hardening

Patch milestone resolving every confirmed issue in
`docs/MILESTONE_REPORT_M4.md` before Dashboard work begins. No new
product functionality — every change is a fix, a UX gap closed, or
cleanup. Full reasoning in `docs/adr/0002-product-shell.md`'s update
section and `docs/ENGINEERING_JOURNAL.md`; this entry is the what.

**1. Nested `<main>` landmark** — `DashboardLayout` rendered its own
`<main>` inside `SidebarInset`, which is itself `<main>`. Fixed by
changing the inner wrapper to a `<div>`. Also gave `not-found.tsx` its
own `<main>` — it sits outside the `(app)` route group, so it never had
one at all. Verified: every route now has exactly one `<main>` (checked
via `page.locator("main").count()` across dashboard, content, and the
404 page).

**2. Mobile search UX** — was `hidden` entirely below `sm`, not
degraded. Added an inline-expanding pattern: a search icon button
swaps the header's breadcrumb/icon row for a full-width `SearchInput`
+ close button (`useState<boolean>` in `AppHeader`, no new
dependencies or primitives). Chose this over `Sheet`/`Dialog`/`Popover`
— see the ADR update for the full reasoning. Verified: autofocus lands
on the input on open, closes cleanly, no console errors.

**3. Navigation architecture** — evaluated `isActive = pathname ===
item.href` per the brief's instruction. Left unchanged: no nested
route exists yet to design or test a better matching rule against, and
guessing ahead of a real need risks getting it wrong. Documented
inline (a comment at the call site) and in the ADR for whoever adds
the first nested route.

**4. Project cleanup** — removed two stray `.gitkeep` files:
`src/components/layout/.gitkeep` (already known from the M4 report)
and `src/hooks/.gitkeep` (a second instance the report's own review
missed — caught by systematically checking every `.gitkeep` in the
project against its directory's actual contents, not just the one
already flagged). All other `.gitkeep` files checked and confirmed
still legitimately empty (reserved for future milestones) — none
removed.

**Also fixed, not in the original 4 confirmed issues but surfaced by
this milestone's own review/verification:**

- **`aria-disabled` on the sidebar's "Help & Support" link** (M4
  report recommendation #6). Added `aria-disabled="true"` (which
  triggers the existing `sidebarMenuButtonVariants` dimmed/
  `pointer-events-none` styling for free — no new CSS needed) plus an
  `onClick` `preventDefault()`. Verified both interaction paths
  independently: mouse clicks are blocked by `pointer-events: none`
  (confirmed via computed style — Playwright's own click simulation
  correctly fails to land on the disabled element), and keyboard
  `Enter` activation is blocked by `preventDefault()` (`pointer-events`
  doesn't affect keyboard-triggered synthetic clicks, so this second
  layer is necessary, not redundant).
- **`EmptyState`'s heading level** (new finding — not in the M4
  report at all). Widened the `axe-core` audit scope to include
  `best-practice`-tagged rules, per the M4 report's own recommendation
  #3, and it immediately surfaced two real violations the narrower
  WCAG-only scope had missed: `heading-order` (dashboard, and by
  extension every placeholder page — `EmptyState`'s hardcoded `<h3>`
  skipped a level after `PageHeader`'s `<h1>`) and
  `page-has-heading-one` (the 404 page had no `<h1>` at all).
  Fixed by making `EmptyState`'s title heading level a `titleAs` prop
  (default `"h2"`, the correct level after a `PageHeader`), with
  `not-found.tsx` and `(app)/error.tsx` passing `titleAs="h1"`
  explicitly, since `EmptyState` is their only heading. Re-verified
  across all 7 routes (6 shell pages + 404): 0 violations, correct
  `h1 → h2` hierarchy everywhere, confirmed via both `axe-core` and a
  direct heading-tag dump per page.

**Process notes**

- This milestone's own verification directly validated the M4
  report's "audit rule-tag scope has a blind spot" risk finding — not
  hypothetically, but by widening the scope and immediately finding
  two more real violations. The recommendation was correct, and acting
  on it caught real bugs.
- One test-harness false positive during verification (a stray
  `SyntaxError` console message on one page load) was investigated and
  confirmed not reproducible via two clean isolated re-runs before
  being dismissed — not waved away on the first look.
- Followed the production-mindset guidance given ahead of this
  milestone: explained the mobile-search alternatives-and-reasoning
  before writing the code (not after), and used static code review for
  the parts of this work that were genuinely reviewable that way,
  reserving the dev-server/Playwright/axe cycle for verifying the
  actual behavioral fixes (landmarks, heading order, disabled-link
  interaction) — which is implementation verification, not reflexive
  review-time tooling.

**Verification** — `typecheck`, `lint`, `build` all pass (see
Validation below). Playwright across desktop/mobile viewports;
`axe-core` widened to `best-practice` tags across all 7 routes: 0
violations. Cleaned up all temporary verification scripts and
uninstalled temporary packages afterward, as established practice.

## 2026-07-10 — Milestone 4: Product Shell

Full application shell: sidebar, header, configuration-driven
navigation, routing, and UX states. No business functionality — every
route is a placeholder. Full reasoning behind the non-obvious decisions
lives in `docs/adr/0002-product-shell.md` and
`docs/ENGINEERING_JOURNAL.md`; this entry is the what/when.

**Discrepancy noted before starting.** The brief referenced
`docs/MILESTONE_REPORT_M3_1.md`, which doesn't exist as a separate
file — Milestone 3.1's findings were amended directly into
`docs/MILESTONE_REPORT_M3.md` (that milestone's actual doc-update
instructions listed `MILESTONE_REPORT_M3.md`, not a new `_M3_1` file).
Treated that file's update note as the M3.1 report for this review.

**New `ui/` primitives** — `sidebar`, `sheet`, `separator` (+
`hooks/use-mobile.ts`), plus `dropdown-menu`, `popover`, `breadcrumb`.
The first three were **not** installed via `npx shadcn add sidebar`
directly — that command reported it would overwrite `button.tsx`,
`input.tsx`, `skeleton.tsx`, and `tooltip.tsx`, all carrying Milestone
3.1's accessibility hardening. Inspected `sidebar.tsx`'s real source
via `--view` first, confirmed it only does normal
`import { Button } from "@/components/ui/button"`, and hand-extracted
the four genuinely new files instead. `dropdown-menu`/`popover`/
`breadcrumb` had no such conflict (pure creates) and were added
normally. Full investigation in `docs/ENGINEERING_JOURNAL.md`.

**Fixed two accessible-name gaps in the vendor source itself** while
integrating: `SidebarTrigger` and `SheetContent`'s close button both
relied on an `sr-only` text child rather than `aria-label`, which
fails to typecheck against this project's stricter `Button` (the
Milestone 3.1 discriminated union). Added explicit `aria-label` to
both during integration — confirms the enforcement catches real gaps,
not just hypothetical ones.

**Navigation model** — `src/lib/navigation.ts`: one config array
(`{ title, href, icon }`, grouped), read by both `AppSidebar`
(rendering) and the header's `getNavTitle()` (breadcrumbs). Future
modules cost one config entry + one route folder; verified true by
construction, not just asserted.

**Theme toggle** — installed `next-themes` rather than hand-rolling
`localStorage` + `useEffect`, specifically to avoid a flash-of-wrong-
theme on load (a real SSR/hydration correctness problem, not a
style preference). `defaultTheme="dark"` per the design brief's
"dark mode first" priority; `enableSystem` still respects OS
preference on first visit. Added `suppressHydrationWarning` to
`<html>` (required — `next-themes` intentionally sets the theme class
after hydration via an injected script, which next-themes documents
as needing this).

**Routing** — `src/app/(app)/` route group: `layout.tsx` (wires
`ProtectedLayout` → `DashboardLayout`), `loading.tsx` (skeleton,
scoped so only the content region re-renders during navigation — the
shell chrome stays mounted across route changes), `error.tsx` (client
error boundary), and six pages (`/`, `/dashboard`, `/content`,
`/wordpress`, `/analytics`, `/settings`), each `PageHeader` +
`EmptyState`. Moved the old root `page.tsx` into the group (can't have
both — they'd collide on `/`). `src/app/not-found.tsx` stays **outside**
the group deliberately — a 404 for a completely unmatched URL
shouldn't assume the dashboard shell applies.

**`ProtectedLayout`** — currently a pass-through, wired into the route
group now so Milestone 8 (Authentication) only has to implement a real
check in one place rather than retrofit every route.

**Bugs found via interaction testing (not caught by `tsc`/lint/build)**
- Opening the user-menu `DropdownMenu` crashed at runtime:
  `MenuGroupContext is missing` — `DropdownMenuLabel` was used outside
  a `DropdownMenuGroup` wrapper. Base UI enforces the ARIA grouping
  relationship via context and throws; Radix-influenced assumptions
  (standalone labels "just work") didn't transfer. Fixed by wrapping
  the label + items in `DropdownMenuGroup`.
- The 404 page's `<Button render={<Link href="/" />}>` logged a Base
  UI warning: rendering as a non-`<button>` while the component still
  expects native button semantics. Fixed with `nativeButton={false}`,
  now the documented pattern for composing `Button` as a navigation
  link. Checked the rest of the codebase for the same pattern — no
  other instances needed it (`SidebarMenuButton`'s different
  `useRender`-based composition isn't affected).
- Both found and fixed via the same temporary-preview-and-revert-style
  Playwright protocol used since Milestone 3, this time run directly
  against the real shell routes (interaction clicks + console-error
  capture), not a throwaway page.

**Self-review finding: missing `<nav>` landmark.** The `sidebar`
primitive renders its content in plain `<div>`s, not a `<nav>` element.
`axe-core` didn't flag it as a hard violation (WCAG 2.4.1 can be
satisfied without a literal `<nav>`, and the `<main>`/`<header>`
landmarks were already present), but "Semantic HTML" was an explicit
self-review checklist item, and a persistent site-navigation region is
exactly what `<nav>` is for. Changed the content-bearing element in
all three `Sidebar` render branches (desktop, mobile/`Sheet`,
`collapsible="none"`) from `<div>` to `<nav aria-label="Main">`.
Re-verified: 0 axe violations, identical screenshot, one `nav[aria-label="Main"]`
landmark present.

**Verification** — Playwright across desktop (1440px), tablet (820px),
mobile (390px, drawer open/closed), and ultra-wide (2560px, content
stays `max-w-7xl`-constrained rather than stretching); sidebar
collapse/expand; theme toggle; notifications popover (reuses
`EmptyState`); user menu (disabled placeholder items rendered as
genuinely disabled, not fake-clickable); keyboard tab order (7
sidebar links → header trigger, matching DOM/visual order); active
route highlighting (confirmed via the actual rendered `data-active`
attribute, not a wrong first-guess selector). `axe-core`: 0 violations
across 6 scenarios (light/dark, desktop/mobile, nested route, 404,
open dropdown) both before and after the `<nav>` fix. `typecheck`,
`lint`, `format:check`, `build` all pass — production bundle is 814 B
per route + 103 kB shared, unchanged in shape from Milestone 3 (no
route pulls in more than it needs).

**Documentation** — `docs/adr/0001-design-system.md` (retroactive,
capturing Milestones 2/3/3.1 decisions) and `docs/adr/
0002-product-shell.md` (this milestone's decisions) created;
`docs/ENGINEERING_JOURNAL.md` created with both backfilled entries
(the `render`-vs-`asChild` and contrast-measurement investigations from
earlier milestones) and this milestone's five real investigations.

## 2026-07-10 — Milestone 3.1: Design System Hardening

Patch milestone addressing every finding from the Milestone 3 report
(`docs/MILESTONE_REPORT_M3.md`, since amended in place with a
resolution note). No new components, pages, or features.

**Method, not just fixes.** Rather than trust the earlier axe-core
numbers or eyeball new values, built a small reusable measurement
script: resolves any CSS color (`oklch()`, `color-mix()`, ...) to
concrete sRGB bytes via a headless Chromium Canvas2D context — this
matters because `getComputedStyle` preserves `oklab()`/`color-mix()`
function notation in its serialization rather than resolving to `rgb()`,
which broke a first attempt at this script. Manually composited
alpha-tinted "badge" backgrounds (Tailwind's `bg-success/10` etc.) over
their real backdrop using the standard "over" formula, then applied the
WCAG relative-luminance contrast formula directly — same math a real
screen produces, not an approximation.

**WCAG contrast — tokens only, per the milestone's explicit
instruction.**
- Measured baseline (matched the Milestone 3 axe-core numbers exactly,
  cross-validating the new method): `success` 3.73:1, `success` on its
  own badge tint 3.32:1, `warning` 2.27:1, `warning` on tint 2.09:1 —
  all failing 4.5:1. Also found two failures axe's single-page audit
  hadn't surfaced, because nothing in that page happened to render the
  exact pairing: `destructive` on its own `/10` badge tint (3.97:1) and
  `muted-foreground` on `muted` background (4.35:1).
- Auto-searched for the lightest (closest to original) OKLCH lightness
  per hue that still clears 4.5:1 against **both** the plain background
  and the tinted-badge composite, then added a small safety margin.
  Light-mode-only changes (dark mode already passed everything):
  `success` L 0.6→0.5, `warning` L 0.75→0.52, `destructive` L
  0.577→0.52, `muted-foreground` L 0.556→0.54.
- Found a second-order issue while verifying: the darkened `warning`
  broke `warning-foreground` (near-black, tuned for the *old*, lighter
  fill) when used as solid-fill text — 3.53:1. Fixed by flipping
  `warning-foreground` to near-white, which also makes it consistent
  with how `success-foreground`/`destructive-foreground` already work
  (both were already near-white). One token-level fix resolved both
  the contrast failure and an existing color-role inconsistency.
- Sanity-checked the remaining grayscale tokens (`primary-foreground`,
  `secondary-foreground`, `accent-foreground`, `foreground`) for
  completeness — all pass with large margins (16:1–20:1), confirming
  the fix scope was correctly bounded to the chroma-bearing tokens the
  milestone named (Success, Warning, Error, Muted text).
- Re-ran the exact same axe-core audit from the Milestone 3 report
  against the corrected tokens, in a real rendered page: **0 violations
  in both light and dark mode** (was 2 light-mode violations before).

**Icon-only button accessibility.** Chose type-level enforcement over
the two conventions the milestone brief offered as examples: `Button`'s
props are now a discriminated union — when `size` is an icon size
(`icon`/`icon-xs`/`icon-sm`/`icon-lg`), `aria-label` becomes a required
prop, a compile-time `tsc` error if missing, not a runtime check or a
lint rule that could be silenced. No new `iconOnly` boolean prop added
— the existing icon-size variants already signal intent unambiguously,
so a second flag would be redundant. Verified the enforcement actually
works both directions with a throwaway `.tsx` file (deleted after):
`<Button size="icon"><Bell /></Button>` fails with a specific, clear
`tsc` error; adding `aria-label` compiles; a normal labeled button is
unaffected.

**Component/data-attribute consistency.**
- `Badge` didn't expose a `data-slot` DOM attribute the way every other
  primitive does — it uses Base UI's `useRender`/`mergeProps`
  polymorphic pattern with an internal `state.slot` instead of a plain
  element. Added a real `data-slot="badge"` attribute via `mergeProps`
  (confirmed via its source: external props always win on conflict, so
  this correctly overrides at the call site too). Required a small,
  documented TypeScript workaround — `mergeProps`'s parameter type
  doesn't declare arbitrary `data-*` keys even though the DOM allows
  them, so an inline object literal trips an excess-property-check
  error; extracting it to an explicitly-typed `const` first avoids that
  without an `any` cast.
- `StatCard` and `StatusBadge` (this project's own hand-written
  `common/` components, not upstream CLI code) were missing `data-slot`
  entirely — added `data-slot="stat-card"` / `data-slot="status-badge"`,
  overriding the `Card`/`Badge` primitives' own internal defaults the
  same way any consumer's override would.
- Confirmed via `grep -L` that every single component file now has
  `data-slot` — zero gaps left.
- `data-state`/`data-disabled`: nothing to change. These come from Base
  UI's own internals automatically and consistently; the
  `@custom-variant data-open`/`data-disabled`/etc. rules already added
  in Milestone 2 hook into that shared convention uniformly. Confirmed
  by review, not assumed.

**Badge review.** Decided: keep as generated, plus the `data-slot` fix
above. Its six variants (default/secondary/destructive/outline/ghost/
link) are already sufficient for `StatusBadge` to compose cleanly;
extending it further would be unused abstraction with no current
consumer.

**Design token audit.** Reviewed the full token list (typography,
spacing, radius, shadows, focus ring, duration, semantic/surface
colors, hover/disabled states, selection, scrollbar) for duplication —
checked programmatically (`awk` + `sort | uniq -d` across `:root`,
`.dark`, and `@theme inline`) rather than by eye. Zero duplicate
declarations found. Hover/disabled states and container widths remain
conventions (Tailwind's own utilities), not new tokens — reconfirmed
this is still the right call, not revisited.

**Process notes**
- All verification (contrast measurement, axe-core re-audit) used the
  same temporary-preview-page-then-revert protocol as Milestone 3 —
  confirmed an empty `git diff` on `page.tsx` against `HEAD` before
  finishing, same as before.
- `npm install --no-save <pkg>` followed by a second, separate
  `npm install --no-save <other-pkg>` call caused npm to prune the
  first package as "extraneous" (not in `package-lock.json`) — lost
  `@axe-core/playwright` partway through. Installing both packages in
  a single command avoids this; worth remembering for the next
  temporary-tooling install.

**Validation** — `typecheck`, `lint`, `format:check`, `build` all pass.
Amended `docs/MILESTONE_REPORT_M3.md` in place with a resolution note
rather than leaving it reading as if the findings were still open.

## 2026-07-10 — Milestone 3: Design System

**Scope decision (asked before starting).** The milestone brief listed
~19 `ui/` primitives and ~9 `common/` components as "Examples:" — not
phrased as a mandatory checklist, and in tension with this project's own
"never generate thousands of lines in one response" / iterative-scope
rule. Asked the user; agreed to build a **core set now, rest on demand**:
9 `ui/` primitives (Button, Input, Textarea, Label, Card, Badge, Avatar,
Skeleton, Tooltip) + a hand-built Typography scale, and 5 `common/`
composites (PageHeader, StatCard, StatusBadge, EmptyState, SearchInput).
Remaining primitives (Dialog, Table, Tabs, Sheet, Accordion, Popover,
Dropdown, Toast) get added in later milestones exactly when a feature
needs them.

**Component architecture**
- `src/components/ui/` — CLI-generated primitives (see below) + a
  hand-built `typography.tsx`.
- `src/components/common/` — reusable, business-agnostic composites.
  Consolidated the brief's overlapping `MetricCard`/`StatCard` examples
  into a single `StatCard` (avoiding "duplicate variants" the milestone
  itself warns against) with a `trend` prop (`up`/`down`/`neutral`,
  color-coded via `success`/`destructive`/`muted`).
- `src/components/layout/` — created empty (`.gitkeep` only), per the
  milestone's explicit "do not build layout components yet" boundary;
  reserved for Milestone 4.
- Removed the now-redundant top-level `src/components/.gitkeep` since
  the directory has real subdirectories.

**Generating the primitives**
- Re-verified `npx shadcn add` still resolves the `nova` preset
  correctly (flagged as a risk in the Milestone 2 report) — confirmed
  via `--dry-run` before generating anything.
- Generated Button, Input, Textarea, Label, Card, Badge, Avatar,
  Skeleton, Tooltip via the CLI. All use `data-slot` conventions, CVA
  variants, semantic color tokens (dark mode "free" as a result), and
  subtle/systematic transitions — no decorative or heavy motion,
  consistent with the design brief.
- Wired `TooltipProvider` into `src/app/layout.tsx` (the CLI's own
  post-install instruction) — this is provider wiring required for
  Tooltip to function at all, not a "layout component" in the
  Milestone 4 sense.
- Added a `loading` prop to `Button` during self-review (spinning
  `Loader2`, `disabled` + `aria-busy`) — "Loading" is an explicitly
  required component state per the brief, and Button is the primitive
  most likely to need it (form submits, async actions). Reuses
  existing Lucide + Tailwind infrastructure rather than adding a
  separate `Spinner` primitive, keeping scope to the agreed core set.

**Design tokens (`src/app/globals.css`)**
- Added `::selection` styling (soft primary-tinted highlight) and a
  minimal styled scrollbar (thin, subtle thumb using `--border`,
  darkens on hover) — both listed explicitly in the milestone's token
  checklist and previously absent.
- Container widths and hover/disabled states are handled as
  **conventions**, not new CSS tokens — Tailwind's default `max-w-*`
  scale and each component's own `hover:`/`disabled:` utilities
  already cover this; adding parallel custom tokens would just
  duplicate what Tailwind provides (documented in `PROJECT.md`).

**Typography scale** — `ui/typography.tsx`: a single polymorphic
`Typography` component (`variant` prop: `display`, `h1`–`h4`, `body`,
`body-sm`, `caption`, `label`, `code`; optional `as` override, sensible
default tag per variant). `body`/`body-sm` deliberately use
`text-sm`/`text-xs` rather than the more conventional
`text-base`/`text-sm`, to match the compact density the generated
primitives already established (`h-8` buttons, `text-sm` inputs) —
one consistent scale, not two competing ones.

**Iconography conventions** — Lucide React only. Documented four usage
categories (navigation, status, action, content) with default sizing
inherited automatically from each primitive's own icon-sizing selector.
Full detail in `PROJECT.md`.

**Visual verification (no pages allowed, verified anyway)**
- Milestone explicitly forbids building pages, but the project's own
  standing rule requires driving UI changes in a real browser before
  reporting them done. Resolved by temporarily replacing
  `src/app/page.tsx` with a preview rendering every new component,
  verifying it, then reverting — confirmed the revert is byte-identical
  to the committed version (empty `git diff`) before finishing.
- No project skill existed yet for running this app; used the `run`
  skill's browser-driven fallback pattern (`chromium-cli` wasn't
  available in this environment, so used Playwright directly — a
  temporary, not-saved `npm install --no-save playwright`, removed
  after verification).
- **Found and fixed a real bug this way**: composed `Tooltip` +
  `Button` using Radix-style `asChild`, which this Base UI-flavored
  shadcn setup doesn't support (confirmed via `TooltipTrigger`'s actual
  type — Base UI's polymorphism is a `render` prop accepting a
  `ReactElement`, not `asChild`). The mistake produced an invalid
  nested `<button>` inside `<button>` and a React hydration error that
  passed `tsc`, ESLint, and `next build` without complaint — only
  caught by actually rendering the page and reading browser console
  errors. Fixed by switching to `render={<Button ... />}`. This is
  worth remembering: **Base UI composition uses `render`, not
  `asChild`** — noted in `PROJECT.md` implicitly via the corrected
  pattern; call it out explicitly if this trips anyone up again.
- Verified: light mode, dark mode (forced via `.dark` class, since our
  dark mode is class-based, not `prefers-color-scheme`), tooltip
  hover-open interaction, keyboard `Tab` focus (confirmed a visible
  focus ring via computed `box-shadow`, not just visual inspection),
  and a 390px mobile viewport (header stacks, StatCards stack, buttons
  wrap — no overflow). Zero console/page errors after the fix.
- Along the way, clarified a subtlety worth documenting: the global
  `:focus-visible` fallback rule from Milestone 2 doesn't actually
  apply to any shadcn-generated primitive, because they all set
  `outline-none` and define their own `focus-visible:ring-3` box-shadow
  ring — and Tailwind's utilities layer always beats `@layer base`
  regardless of source order. Not a bug (every primitive has a visible,
  confirmed focus ring, just via a different mechanism); the global
  rule still protects any future plain HTML element. Documented in
  `PROJECT.md` so it doesn't look broken to a future reader checking
  the CSS in isolation.
- A `.next` build cache corruption (`EINVAL: invalid argument, readlink`
  on a OneDrive-synced path) blocked the first dev server start —
  resolved with `rm -rf .next`. Environment-specific (OneDrive sync
  interfering with Next's cache directory), not a code issue.
- Cleaned up thoroughly: temp verification scripts and the `playwright`
  package removed, `page.tsx` reverted (verified empty diff),
  `.next` cache untouched by git either way.

**Self-review findings (fixed within this milestone)**
- Button lacked a `loading` state despite it being an explicit
  requirement — added (see above).
- No other gaps found: every primitive already supports disabled,
  hover, focus-visible, keyboard navigation, and error states
  (`aria-invalid`) via the CLI's own generated code.

**Verification** — `typecheck`, `lint`, and `format:check` all pass;
all 14 new/modified component files under 110 lines (well within the
~300-line guideline, largest is `avatar.tsx` at 109).

## 2026-07-10 — Milestone 2: Project Foundation

**Dependencies installed**
- UI: `shadcn/ui` CLI (v4.13.0, dev-only), `@base-ui/react`,
  `lucide-react`, `class-variance-authority`, `clsx`, `tailwind-merge`,
  `tw-animate-css`.
- Forms/validation: `react-hook-form`, `zod`, `@hookform/resolvers`.
- State: `zustand`. Server state: `@tanstack/react-query`. Tables:
  `@tanstack/react-table`. Charts: `recharts`.
- Dev tooling: `prettier`, `prettier-plugin-tailwindcss`,
  `eslint-config-prettier`, `husky`, `lint-staged`.
- None of these are wired into application code yet — Milestone 2 is
  foundation only, no business features per scope.

**shadcn/ui configuration**
- The current shadcn CLI (v4.13.0) is a significant rework from the
  classic version: components are chosen from a `base`/`radix`
  primitive library and a named color preset (`nova`, `vega`, etc.)
  pulled from shadcn's registry, rather than the old
  `new-york`/`default` style + base-color prompts.
- Initialized with `--base base` (Base UI primitives — the CLI's
  current default, and the actively maintained successor built by the
  Radix/Floating UI team) and `--preset nova`, `--css-variables`,
  neutral base color, `src/` + TS + import aliases auto-detected.
- `init` auto-generated `src/components/ui/button.tsx` as a side
  effect — removed, since Milestone 2 scope explicitly excludes
  generating components (that's Milestone 3).
- The CLI also listed itself (`shadcn`) as a runtime `dependency` —
  moved to `devDependencies`; it's a codegen tool, never imported at
  runtime.

**Theme foundation (`src/app/globals.css`)**
- Kept the CLI's generated neutral OKLCH color scale (background,
  foreground, primary, secondary, muted, border, card, popover,
  accent, sidebar, chart-1..5) — it already matched the "neutral,
  dark-mode-first" brief. Dark mode is class-based (`.dark`), not
  `prefers-color-scheme`, so a manual toggle can be added later.
- Did **not** import the CLI's bundled `shadcn/tailwind.css` — it
  bundles genuinely useful infrastructure (data-state custom variants,
  accordion keyframes) alongside decorative "shimmer" text and
  "scroll-fade" mask utilities that contradict the brief's "no
  unnecessary animations" rule. Inlined only the needed variants/
  keyframes/`no-scrollbar` utility directly instead, avoiding an
  ongoing undocumented coupling to the CLI package's internal file.
- Added tokens the preset didn't include: `success`/`warning` (plus
  foreground pairs, light + dark), `destructive-foreground` (missing
  from the generated preset entirely), a soft shadow scale
  (`shadow-xs/sm/md/lg`, lighter in light mode / more opaque in dark),
  and `duration-fast`/`duration-slow` transition tokens.
- Added an explicit `:focus-visible` rule (2px ring, 2px offset) for
  keyboard-navigation accessibility, on top of the CLI's default
  low-emphasis `outline-ring/50`.
- Fixed a bug in the generated file: `--font-sans` was mapped to
  `var(--font-sans)` — a self-reference to a variable defined nowhere
  else, meaning `font-sans` would have silently fallen back to the
  browser default. Rewired it to the actual font stack.
- Fixed an inconsistency: `.dark` mode's `--sidebar-primary` carried a
  stray blue hue (`oklch(0.488 0.243 264.376)`) left over from the
  preset, while every other token was neutral grayscale — neutralized
  to match the same light/dark inversion pattern used by `--primary`.
- Tailwind's default spacing scale is used as-is (not reinvented) —
  Tailwind v4 already ships a sensible `--spacing` base unit.

**Typography**
- Geist (primary) + Inter (explicit fallback), both self-hosted via
  `next/font/google` — zero layout shift, no external font requests.
  Verified in the compiled build that Inter isn't eagerly preloaded
  (it's a pure CSS fallback, only fetched if Geist fails).

**Dev tooling**
- Prettier configured (`.prettierrc.json`, `.prettierignore`) with
  `prettier-plugin-tailwindcss` for automatic class sorting;
  `eslint-config-prettier` added to eliminate formatting-rule
  conflicts between ESLint and Prettier.
- Husky + lint-staged wired to a pre-commit hook: ESLint --fix +
  Prettier on staged JS/TS, Prettier on staged JSON/CSS/MD.
- Added `typecheck` (`tsc --noEmit`), `format`, `format:check` npm
  scripts. `typecheck` and `lint` are both CI-ready as standalone
  scripts.

**VSCode / environment / GitHub**
- `.vscode/settings.json` + `extensions.json`: Prettier as default
  formatter, format-on-save, ESLint auto-fix on save, Tailwind
  IntelliSense class-regex support for `cn()`/`cva()`. Recommended
  extensions only (ESLint, Prettier, Tailwind CSS IntelliSense) — no
  personal preferences.
- `.env.example` with the four requested placeholders, no secrets.
- `.github/`: bug report + feature request issue templates, PR
  template with a lint/typecheck/build checklist, `CODEOWNERS`.

**Issues found and fixed during self-review**
- `.gitignore`'s `.env*` pattern was also silently ignoring
  `.env.example` itself, which defeats its purpose as a committable
  template — added `!.env.example` negation.
- Confirmed Husky's internal `.husky/_/` helper scripts are
  self-ignored via their own `.gitignore` (only `.husky/pre-commit` is
  tracked), and that `package-lock.json` correctly reconciled `shadcn`
  as a dev dependency after the manual `package.json` edit.

**Known items for later milestones**
- `components.json`'s `"style": "base-nova"` ties `npx shadcn add` to
  a specific registry preset name; if the CLI renames/removes it in a
  future version, re-running `add` could behave unexpectedly. Low risk,
  worth a quick check before Milestone 3.
- `success`/`warning` OKLCH values are reasonable placeholders, not
  yet visually verified against real components — revisit once
  Milestone 3 adds badges/alerts that use them.
- A moderate `postcss` audit advisory remains, nested inside Next.js's
  own `node_modules` (noted in Milestone 1) — still not fixable
  without a breaking Next.js downgrade; recheck on the next Next.js
  patch release.

**Verification**
- `npm run typecheck`, `npm run lint`, `npm run format:check`,
  `npm run build` all pass. Dev and production servers both smoke
  tested; compiled CSS spot-checked to confirm the font stack and
  focus-ring rule compiled as intended.

## 2026-07-10 — Milestone 1: Project Initialization

- Scaffolded the project with `create-next-app`: Next.js 15 (pinned to
  15.5.20 — `latest` resolved to an unwanted 16.x canary), React 19,
  TypeScript, Tailwind CSS 4, ESLint 9, App Router, `src/` directory,
  `@/*` import alias.
- Removed auto-generated `AGENTS.md`/`CLAUDE.md` boilerplate that
  referenced the canary Next.js version — inaccurate once pinned to
  stable 15.5.20.
- Replaced the default starter homepage with a minimal placeholder and
  updated root metadata (title/description) to reflect WP Studio.
- Removed unused template SVG assets from `public/`.
- Built the feature-first `src/` skeleton: shared `components/`,
  `hooks/`, `lib/`, `services/`, `store/`, `types/`, `utils/`,
  `styles/`, plus `features/{dashboard,wordpress,content,analytics,
  settings,authentication}/` each with its own `components/`, `hooks/`,
  `services/`, `types/`, `utils/`. No business logic added.
- Added `docs/` with `PROJECT.md`, `ROADMAP.md`, `CODING_STANDARDS.md`,
  `DEVLOG.md`.
- Verified `tsc --noEmit`, `next lint`, and `next build` all pass.
