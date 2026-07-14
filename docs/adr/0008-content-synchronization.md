# 0008 — Content Synchronization Architecture

**Status:** Accepted (Milestone 10)

## Decision

Introduce a dedicated `App\Services\ContentSync\` layer — a generic
synchronization *engine* (`ContentSyncService`) parameterized by a
per-content-type `ContentTypeMapper` contract, with `WordPressPostMapper`
as the only concrete implementation this milestone builds. Extend the
existing `posts` table with sync-tracking columns rather than creating
a parallel table. Reuse the existing `PostController`/`PostPolicy`/
`PostResource` for reading synced content instead of adding a
duplicate route surface. Make synchronization idempotent via a
content-hash comparison, not a timestamp heuristic alone. Keep the
entire operation synchronous, bounded, and named as the seam a future
queued job attaches to unchanged.

## Context

**Where this sits in the project.** Milestone 9 gave `Site` a real,
verified connection to an external WordPress installation but never
read anything back from it beyond connection metadata (theme, plugin
count, ...). `docs/PROJECT.md`'s own Known Limitations named this gap
directly: "nothing writes to a connected WordPress site yet (that's
Content Management and Publishing, both future milestones)." This
milestone is the *read* half of that gap — Publishing (writing back to
WordPress) remains future scope, named but not built.

**A pre-existing collision this milestone had to resolve.** `Post` has
existed since Milestone 7 as WP Studio's own first-class,
manually-created content entity — full CRUD, a Policy, a Resource, a
Form Request pair — but had never had a frontend consumer. This
milestone is the first time `Post` gets a UI, and it arrives via sync,
not manual creation. The real architectural question wasn't "how do we
model synced content" in isolation; it was "does a post synced from
WordPress belong in the same table as a post a user typed into WP
Studio directly, or somewhere else."

**Scope boundary.** Per the brief: browse, synchronize, inspect
metadata, refresh, view sync status. Explicitly *not* this milestone:
modifying WordPress content, queued/background processing, scheduled
sync, generalizing to Pages/Media/Categories/Tags beyond the engine
shape itself.

## Alternatives Considered

**Extend the existing `posts` table vs. a new, parallel table for
synced content.** A separate table (e.g. `wordpress_posts` or a
polymorphic `synced_content` table) would cleanly separate "things WP
Studio users typed" from "things pulled from an external system," and
was seriously considered. Rejected in favor of extending `posts`:
every existing consumer of `Post` — `PostController`, `PostPolicy`,
`PostResource`, and the (not-yet-built) Publishing milestone — treats
"a post" as one kind of thing regardless of where its content
originated. A synced post and a manually-drafted post are the same
domain concept to everything downstream of this layer; forking them
into two tables would mean forking every future consumer too, for no
real gain today. `wordpress_post_id` is nullable specifically so a
manually-created post (no WordPress origin) and a synced post (has
one) coexist in the same table without either shape lying about the
other's absent fields.

**A generic polymorphic content table now vs. a generic sync *engine*
now, concrete content types later.** The brief explicitly requires
this layer to generalize to Pages/Media/Categories/Tags/Custom Post
Types without hardcoding "Posts." Building a single polymorphic schema
now that could hold any future content type was considered and
rejected — this is the identical trap `docs/adr/0005-domain-model.md`
already named and avoided for the "AI Jobs" table: guessing a
one-size-fits-all schema before a second real content type exists to
validate it against would very likely mean a breaking migration the
moment Pages or Media actually gets built (different field shapes,
different WordPress endpoints, different local persistence needs).
Instead, the genericity lives in the **engine**:
`App\Services\ContentSync\Contracts\ContentTypeMapper` is a small
interface (endpoint, mapping, upsert, per-type sync-count query);
`ContentSyncService::sync()` knows nothing about "posts" at all — it
orchestrates pagination, hashing, and result tallying against whatever
mapper it's given. `WordPressPostMapper` is the only implementation
today. Adding Pages later means a new `pages` table (or whatever shape
Pages actually needs) plus a new `WordPressPageMapper` — zero changes
to `ContentSyncService`.

**Idempotency — timestamp comparison vs. content hash.**
`wordpress_modified_at` alone was considered as the sole
change-detection signal (skip if the incoming `modified_gmt` isn't
newer than the stored value). Rejected as the *only* mechanism: a hash
of the mapped, change-relevant attributes (`sync_hash`, sha256) is
stored alongside `wordpress_modified_at` and is what synchronization
actually compares — this catches a genuine content change even if a
WordPress site's clock is wrong or `modified_gmt` wasn't updated for
some reason, and cheaply short-circuits a write when nothing actually
changed rather than trusting a single external timestamp field to be
authoritative. `wordpress_modified_at` is still stored and rendered
(useful to a user deciding whether to re-sync), just not the sole
gate.

**Route surface — new nested `GET /sites/{site}/posts` routes vs.
reusing the existing `GET /posts?site_id=`.** The brief's own example
route list included a nested `sites/{site}/posts` shape. Reusing the
existing endpoint instead: `PostController::index` already scopes to
the current workspace's sites and already accepts a `site_id` filter
(`IndexPostsRequest`, built in Milestone 7) — a nested alias route
would duplicate that query logic for a cosmetic URL difference. The
only genuinely new routes this milestone adds are the two that have no
existing home: `POST /sites/{site}/sync` and
`GET /sites/{site}/sync-status`.

**Sync authorization — `view` vs. `update`/`create` policy checks.**
Considered gating `POST /sites/{site}/sync` behind
`SitePolicy::update` (owner/admin only), matching `disconnect`'s
posture. Chosen `view` instead (any workspace member), matching how
Milestone 9 already classified `verifyConnection`/`refreshMetadata`:
sync pulls data in and doesn't touch site-level attributes or stored
credentials, the same "read-adjacent" reasoning
`docs/adr/0007-wordpress-integration-architecture.md` already applied
to those two actions. Reusing the classification, not inventing a new
one.

## Domain Model Changes

**`posts` table** (new migration, additive to the Milestone 7 table):
`wordpress_post_id` (nullable, unique per `site_id`), `wordpress_modified_at`,
`wordpress_url`, `sync_status` (`synced` | `failed`), `sync_hash`,
`last_synced_at`. The unique composite index on
`(site_id, wordpress_post_id)` is the actual duplicate-import guard —
SQL unique indexes don't constrain `NULL` against `NULL`, so
manually-created posts (`wordpress_post_id IS NULL`) are unaffected.

**`sites.last_synced_at`** (new column, same migration pattern as
`last_connected_at`/`last_checked_at` from Milestone 9): when the
*whole-site* sync operation last completed successfully. Distinct from
the per-post `last_synced_at` above.

**No new `SiteStatus` case needed.** `SiteStatus::Syncing` already
existed in the enum, added in Milestone 6/7 but never used by any code
path until now — flagged during this milestone's own architecture
review as a signal that synchronization had been anticipated. It
remains unused: a synchronous, single-request sync doesn't have a
meaningful window to report an in-progress state to a second observer,
since the request that triggered it is the only thing waiting on it.
Left in place, undisturbed, as the natural value a future asynchronous
sync (Milestone 11) would set while a queued job is running — see
Future Evolution.

## Chosen Solution

### Backend

```
App\Services\ContentSync\
├── ContentSyncService.php          — generic orchestrator
├── Contracts\ContentTypeMapper.php  — the seam for future content types
├── Mappers\WordPressPostMapper.php  — the only implementation today
├── DTO\
│   ├── MappedContent.php            — one mapped item + its hash
│   ├── SyncResultDTO.php            — outcome of one sync run
│   └── SyncStatisticsDTO.php        — read-model for sync-status
├── Enums\SyncOutcome.php            — created | updated | skipped
└── Exceptions\ContentSyncException.php  — extends ApiException
```

`ContentSyncService::sync(Site $site, ContentTypeMapper $mapper)`:
resolves the site's stored credential (throws `ContentSyncException`
if none — the same "reconnect to continue" posture
`SiteConnectionService::syncFromWordPress()` already takes), runs the
existing `UrlSafetyValidator` SSRF check, then pages through
`WordPressClientContract::fetchCollection()` (new contract method,
below) calling `$mapper->map()` → `$mapper->upsert()` per item,
tallying created/updated/skipped/failed into a `SyncResultDTO`, and
dispatching a new `ContentSynced` event (mirroring
`SiteConnected`/`LogSiteConnected` from Milestone 6/7 — the same
domain-event pattern, a second real consumer of it). A total failure
to reach WordPress at all (can't fetch even the first page) marks the
site `Error` with a stored `connection_error`, identical to how
`SiteConnectionService::syncFromWordPress()` already handles verify/
refresh failures — the same failure-reporting mechanism, reused, not
reinvented. A single item failing to map (malformed WordPress JSON)
is recorded in `SyncResultDTO`'s `errors` and the batch continues —
this does *not* touch `Site.status`, since the connection itself is
fine.

`WordPressPostMapper` owns everything specific to "post": the
WordPress endpoint (`/wp-json/wp/v2/posts`), skipping `trash`-status
items entirely (not imported), mapping WordPress's status vocabulary
onto the existing `PostStatus` enum (below), computing `sync_hash`,
and the actual `Post::query()` upsert keyed on
`(site_id, wordpress_post_id)`.

**WordPress status → `PostStatus` mapping** (WordPress's vocabulary
doesn't line up 1:1 with the enum Milestone 7 already established):

| WordPress `status` | Mapped `PostStatus` |
| --- | --- |
| `publish` | `Published` |
| `pending` | `InReview` |
| `draft`, `future`, `private` | `Draft` (documented fallback — no dedicated state exists for the latter two yet) |
| `trash` | not imported |

**`WordPressClientContract` gains one new method,** `fetchCollection()`
— reusing `HttpWordPressClient`'s existing private `request()`/retry/
timeout/authentication machinery (extracted a shared
`assertSuccessfulJsonArray()` helper so `fetchRequired()` and
`fetchCollection()` share response-validation logic rather than
duplicating it) — returning a `WordPressCollectionPage` (items +
`X-WP-TotalPages`). `fetchSiteInfo()` is unchanged. This is the same
"one contract, one HTTP client behind it" shape
`docs/adr/0007-wordpress-integration-architecture.md` established,
extended with a second operation because fetching a single site's
metadata and fetching a paginated content collection are genuinely
different operations — unlike M9's single-method contract, which was
deliberately kept to one method because "verify" and "refresh" were
the *same* operation wearing two names.

**API** (`routes/api_v1.php`, inside the existing
`auth:sanctum` + `ResolveCurrentWorkspace` group):

```
POST /api/v1/sites/{site}/sync          — throttle:wordpress-connection
GET  /api/v1/sites/{site}/sync-status
```

Both call `$this->authorize('view', $site)` — see Alternatives
Considered. `ContentSyncController` never talks to
`WordPressClientContract` directly; it depends only on
`ContentSyncService`, matching the "controllers never talk to
WordPress directly" rule `docs/adr/0007-wordpress-integration-architecture.md`
already established for `SiteController`.

`SyncResultResource`/`SyncStatisticsResource` render the two DTOs into
the existing snake_case JSON convention — the same pattern
`DashboardSummaryResource` already established for wrapping a plain
DTO rather than serializing it directly (a raw `readonly` DTO's
camelCase properties would otherwise leak into the response, breaking
every other endpoint's snake_case contract).

### Frontend

Stays inside `src/features/wordpress/` — content sync is scoped per
connected site, not a standalone top-level concept, matching how the
feature is already organized. New nested routes:
`/wordpress/[id]/posts` and `/wordpress/[id]/posts/[postId]` — this
app's second level of route nesting, following the same pattern
`/wordpress/[id]` established in Milestone 9. `src/services/api/
posts.service.ts` is new (`Post` has had a backend since Milestone 7
but never a frontend consumer until this milestone); `syncSite()`/
`getSyncStatus()` were added to the existing `sites.service.ts`
instead of a new file, since they're site-scoped actions alongside
`disconnectSite`/`verifySiteConnection`/`refreshSiteMetadata`. Four
new components (`PostsTable`, `PostDetail`, `SyncButton`,
`SyncSummary`) compose only existing primitives (`Card`, `StatusBadge`,
`LoadingState`, `EmptyState`, `PageHeader`) — no new UI primitive was
needed. `SyncButton` and `SyncSummary` are separate, independently
data-fetching components coordinated entirely through TanStack Query
cache invalidation (`useSyncSite`'s `onSettled` invalidates the sites
list, the site's posts, and the site's sync-status queries) rather
than shared local state — the same coordination mechanism
`useDisconnectSite`/`useVerifyConnection` already use to keep
`SitesList` in sync with actions taken elsewhere.

## Security

Every existing layer carries over unchanged: `auth:sanctum` +
`ResolveCurrentWorkspace` scope every sync route to the caller's
workspace; `SitePolicy::view` gates both new actions; `UrlSafetyValidator`
runs before `ContentSyncService` makes any outbound request, identical
to the SSRF check every other WordPress-facing action already runs;
credentials are read via the same encrypted `SiteCredential` relation,
never duplicated or logged. `POST /sites/{site}/sync` carries the
existing `wordpress-connection` rate limiter (10/minute) — sync makes
real outbound HTTP requests to an external, user-supplied host, the
same abuse surface `connect`/`verify`/`refresh-metadata` already
share the limiter to close.

## Performance

**Named, accepted limit: per-item upsert queries, not a batch
operation.** `WordPressPostMapper::upsert()` runs one lookup query per
WordPress item to check for an existing row before deciding
create/update/skip — for a 100-item page, that's up to 100 `SELECT`
queries per sync call. Accepted for this milestone: at today's real
usage (a handful of connected sites, sync triggered manually by a
user clicking a button, not run continuously) this is not a measured
problem, and a real batch-upsert redesign is exactly the kind of
optimization worth deferring until a real workspace's post volume
makes it one — the same "don't build for a scale that doesn't exist
yet" discipline `docs/adr/0005-domain-model.md` already applied to
deferring pagination on the Sites/Posts index endpoints. Flagged here,
not silently accepted.

**Bounded pagination, not unbounded.** `ContentSyncService` pages
through WordPress's own `page`/`per_page` pagination
(`per_page=100`), capped at 20 pages (2,000 posts) per sync call — a
safety bound on a single synchronous HTTP request, not a real product
limit. A site with more posts than that gets its first 2,000 synced
per call; repeated syncs don't re-process what's already
hash-unchanged (see Idempotency below), so this is a soft, self-
correcting limit today, not data loss. Documented as the concrete seam
Milestone 11's queued version removes.

## Idempotency, Concretely

1. Fetch a page of raw WordPress post JSON.
2. `WordPressPostMapper::map()` normalizes it to local attributes and
   computes `sync_hash` (sha256 of the mapped, change-relevant
   fields).
3. Look up `Post::where('site_id', ...)->where('wordpress_post_id', ...)->first()`.
4. No existing row → insert, tally `created`.
5. Existing row, same `sync_hash` → skip entirely (no write), tally
   `skipped`.
6. Existing row, different `sync_hash` → update, tally `updated`.

Verified directly in tests: syncing the same fixture data twice in a
row produces zero new rows and an all-`skipped` result on the second
call; changing one field and re-syncing produces exactly one `updated`
row, not a duplicate.

## Rejected Alternatives

**A connection-history/audit table logging every sync attempt.**
Rejected for the same reason
`docs/adr/0007-wordpress-integration-architecture.md` already rejected
it for connection attempts: `Site.last_synced_at` plus each post's own
`sync_status`/`last_synced_at` already answer "is this working, and
what's the current state" — what today's UI actually needs. A real
audit table is plausible future scope once a real usage pattern
justifies it, not before.

**Storing full WordPress post content/body this milestone.** The
brief's stated goal is browse/synchronize/inspect metadata/refresh —
not editing. Only title, mapped status, dates, and the public URL are
stored; the raw HTML body is not fetched or persisted. Storing full
content ahead of an actual editing/Publishing feature needing it would
be exactly the kind of speculative scope this project's standing
"no premature abstraction" rule exists to prevent — revisit when a
future Publishing milestone actually needs to render or edit post
content, not before.

## Future Evolution

- **Milestone 11 (Background Jobs & Queues):** `ContentSyncService::sync()`
  is unchanged by the move to async — a queued job calls it instead of
  a controller calling it inline, the same seam
  `docs/adr/0007-wordpress-integration-architecture.md` already
  documented for `SiteConnectionService`. `SiteStatus::Syncing`
  (present since Milestone 6/7, unused until a job can meaningfully
  report "in progress" to something other than the request that
  triggered it) is the natural status a queued sync sets for its
  duration. The 20-page synchronous cap is removed once pagination
  itself runs inside a worker rather than inside one HTTP request.
- **Pages, Media, Categories, Tags, Custom Post Types:** each is a new
  `ContentTypeMapper` implementation plus whatever local table shape
  that content type actually needs — zero changes to
  `ContentSyncService`. Not built now, per the same reasoning
  `docs/adr/0005-domain-model.md` applied to deferring the "AI Jobs"
  table: guessing those shapes without a second real content type to
  validate against would likely mean a breaking migration later.
- **Publishing** (future milestone): the inverse operation — writing
  local `Post` changes back to WordPress — is real future scope this
  milestone doesn't touch. `Post.wordpress_post_id` is exactly the
  field a future Publishing flow needs to know *which* WordPress post
  to update.
- **Per-item upsert performance**: named above, revisit if a real
  workspace's post volume makes 100 sequential lookup queries per page
  a measured problem, not before.
