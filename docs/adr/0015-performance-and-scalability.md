# 0015 — Performance & Scalability

**Status:** Accepted (Milestone 17)

## Decision

Measure first, fix what's measured, document what isn't justified yet —
the same discipline `docs/adr/0005-domain-model.md` and
`docs/adr/0008-content-synchronization.md` already committed this
project to when they named these exact gaps and explicitly deferred
them pending "a measured problem." This milestone is that measurement.
Two real, now-quantified problems got fixed: unbounded Posts/dashboard-
drafts queries (pagination) and `WordPressPostMapper::upsert()`'s
per-item N+1 (batch preload). One real, measured frontend win: the
dashboard's chart library was blocking initial load for a widget below
the fold. Redis-backed caching was evaluated against real query timings
and **not implemented** — the data doesn't justify it yet, and adding
it anyway would be exactly the premature optimization ADR-0005 and this
milestone's own roadmap entry both warn against.

## Methodology: A Realistic Dataset, Not a Guess

`DemoDataSeeder` is intentionally small (1 workspace, 4 sites, ~11
posts, 56 analytics snapshots — see `docs/PROJECT.md`'s Known
Limitations) because it's a demo, not a load-test fixture. Profiling
against it would have measured nothing. Backed up the real dev database
(`cp database.sqlite /tmp/m17-dev-backup.sqlite`), then temporarily
inflated it — 34 sites, 6,012 posts, 2,756 analytics snapshots, roughly
the shape of a workspace that's been syncing real WordPress sites for a
while — and measured every candidate hot path with
`DB::listen()` + `microtime(true)` directly against it, not a guess
about what "should" be slow. Restored the original dev database before
this milestone's own validation and live browser check, so the demo
experience shipped is unaffected by the profiling data.

## What Was Measured

| Path | Result | Verdict |
|---|---|---|
| `DashboardService::summary()` | 5 queries, 12ms | Fast — no fix |
| `DashboardService::recentActivity()` | 6 queries, 7ms | Fast — no fix |
| `Post::query()->...->get()` (Posts index, unpaginated) | 4 queries, **142ms for 6,012 rows** | Real problem — fixed |
| `WordPressPostMapper::upsert()` × 100 | **300 queries, 1,297ms** | Real problem — fixed |

`DashboardService` was already correctly eager-loaded (Milestone 10.1's
own review) — nothing to fix there even at this scale. The other two
were both *named* debts (`docs/adr/0005-domain-model.md`'s deferred
pagination, `docs/adr/0008-content-synchronization.md`'s deferred
batch-upsert), now quantified rather than assumed.

## Fix 1: Posts Index Pagination

`PostController::index()` returned every matching row with no `LIMIT` —
fine at demo scale, genuinely wrong at 6,000+ rows (142ms and rising
linearly with a workspace's real post count). Added standard
page-number pagination: `page`/`per_page` (`per_page` capped at 100,
default 50) on `IndexPostsRequest`, `Post::query()->paginate()` in the
controller, and a `meta.pagination` block
(`current_page`/`per_page`/`total`/`last_page`) on the existing
`ApiResponse` envelope — additive, no breaking change to `data`'s shape
or any other endpoint.

**Frontend**: `PostsTable` (the one real consumer of the full list, at
`/wordpress/[id]/posts`) now holds `page` state and renders Previous/
Next controls plus a "Page X of Y · N posts" caption, only when
`last_page > 1` — a single site with under 50 posts (every demo site
today) renders identically to before. `useSitePosts` uses
`placeholderData: keepPreviousData` (TanStack Query v5) so paging
doesn't flash a loading state between pages.

**`RecentDrafts`** (the dashboard widget) had the identical unbounded
query — a workspace with many drafts would render all of them on the
dashboard, which is wrong for a "recent" widget regardless of the
Posts-index fix. Capped it at `per_page=5` server-side rather than
building pagination UI a dashboard widget doesn't need — the simpler
fix for what's actually a "show me the most recent few" use case.

**Sites and Media indexes have the identical unbounded-query shape and
were deliberately not touched this milestone** — no measured problem.
A workspace's connected-sites count is realistically bounded (single
digits to tens, not thousands) and there's no realistic multi-hundred
Media dataset to profile against today. Fixing them now would be the
same reflexive, unmeasured optimization this ADR argues against
elsewhere — named here as real future work, not silently skipped.

## Fix 2: `WordPressPostMapper`'s N+1

`upsert()` ran one `SELECT` per WordPress item to check for an existing
row before deciding create/update/skip — up to 100 queries per synced
page, up to 2,000 per full sync (`MAX_PAGES=20 × PER_PAGE=100`).
Measured: 300 queries / 1,297ms for 100 items (3 queries/item: the
lookup, the insert, and the featured-image check).

Added `preloadExisting(Site $site, array $wordpressIds): void` to the
`ContentTypeMapper` contract — `WordPressPostMapper` batch-loads every
existing post for the current page's WordPress IDs into a
`keyBy('wordpress_post_id')` array in one query;
`ContentSyncService::sync()` calls it once per page (after fetching the
page, before the `foreach`), and `upsert()` reads the in-memory cache
instead of querying. Measured after the fix: **201 queries / 1,094ms**
for the same 100 items — the 100 per-item lookups collapsed into 1
batch query (300 → 201, exactly the expected 100-query reduction). The
remaining 2 queries/item (insert + featured-image check) are real,
necessary per-row work, not N+1 — a further reduction would mean
batching *inserts*, which is real future scope if sync volume ever
makes it a measured problem, not assumed here.

The wall-clock improvement is modest against local SQLite (SQLite has
near-zero per-query round-trip cost); the query-*count* reduction is
the actual fix — it's what will matter once this runs against a real
network-latency production database (Milestone 19's MySQL/PostgreSQL
migration), where 100 saved round-trips is a real, not theoretical,
win.

`WordPressPostMapper` is still the only `ContentTypeMapper`
implementation (`docs/adr/0008-content-synchronization.md`'s
interface-first design paid off here — one new contract method, one
implementation to update, zero changes to the orchestrator's control
flow beyond the single `preloadExisting()` call site).

## Fix 3: Dashboard Bundle — recharts Off the Critical Path

Production build measurement (`npm run build`'s own route-size table)
showed `/dashboard` at 249kB First Load JS against 103–188kB for every
other route — `recharts` (a real, sizeable charting library), imported
eagerly at the top of `AnalyticsPreview` and eagerly imported into
`dashboard/page.tsx`, meant every dashboard load shipped the full chart
library before the rest of the page — KPI cards, recent activity,
system health — could even hydrate, for a chart that's one widget among
nine.

Split `AnalyticsPreview` into its own chunk via `next/dynamic({ ssr:
false })`, following the exact pattern this codebase already uses for
`ReactQueryDevtools` in `query-provider.tsx`. Since `next/dynamic`'s
`ssr: false` isn't permitted inside a Server Component,
`dashboard/page.tsx` stays a server component and instead imports a new
small client wrapper (`analytics-preview-lazy.tsx`) that does the
dynamic import — the loading placeholder mirrors the real card's shape
(header + `h-48` skeleton) so nothing visibly shifts on swap-in.
Measured result: **First Load JS 249kB → 144kB (−42%)**, page-specific
JS 120kB → 15.1kB. recharts now loads as its own async chunk, fetched
only once the dashboard actually renders, off the path of every other
widget's hydration.

## Redis: Evaluated, Not Implemented

`docker-compose.yml`'s `redis` service has existed, unused, since
Milestone 15 (`CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` all
stay on `database`) — that ADR named "real integration is Milestone
17's job." This milestone did that evaluation: `DashboardService`'s two
aggregate methods, the most obvious caching candidates, measured at
5–12ms even at 34 sites/6,012 posts/2,756 snapshots — already fast
enough that a cache layer would add invalidation complexity (when does
a cached dashboard summary go stale relative to a sync completing?)
for a saving too small to matter. No other endpoint profiled this
milestone showed a repeated, expensive, cacheable read — the two real
problems found (Posts pagination, the sync N+1) are query-*shape*
problems a cache wouldn't fix, only mask.

**This is the decision, not a placeholder for one**: Redis stays
present-but-unused in the Docker Compose stack, matching Milestone 15's
original framing. Revisit if a future milestone's real usage data (not
a hypothetical) shows a specific, repeated, expensive read — the
`CACHE_STORE=redis` env var and running container are one line away
whenever that happens.

## Rejected Alternatives

**Cursor-based / infinite-scroll pagination for Posts.** Considered for
a smoother scrolling UX. Rejected: standard page-number pagination is
simpler to implement, test, and reason about, and is sufficient for a
list this size — infinite scroll's added complexity (intersection
observers, accumulated-state management, scroll-position restoration)
demonstrates the same competency as page numbers for a portfolio
project, per this project's standing "prefer the simpler approach"
guidance.

**Paginating Sites and Media alongside Posts.** Same unbounded-query
shape exists on both. Rejected for this milestone specifically because
neither has a measured problem — Sites is realistically bounded per
workspace; Media has no real dataset to profile against. Fixing them
now would be pattern-matching on "looks similar to something I just
fixed," not evidence — the opposite of this milestone's own stated
discipline.

**Redis caching "since the infrastructure is already there."** The
container's existence isn't itself a justification — see the section
above. Adding a cache layer without a measured hot path to justify it
is the textbook premature optimization `docs/adr/0005-domain-model.md`
already committed this project to avoiding.

**Preloading/prefetching the recharts chunk during idle time** (e.g.
`next/link`-style prefetch or a `requestIdleCallback` warm-up).
Considered to avoid the brief loading-skeleton flash on first dashboard
visit. Rejected as unnecessary complexity for a sub-second async chunk
fetch on a local/typical broadband connection — the skeleton itself
already prevents layout shift, which was the only real user-facing
concern.

## Validation

- `php artisan test` — **142/142 passing**, unchanged.
- `./vendor/bin/pint --test` (full-repo) — clean.
- `npm run typecheck` / `npm run lint` — clean.
- `npm run test` — **20/20 passing**, unchanged.
- `npm run build` — clean; dashboard route First Load JS confirmed at
  144kB (down from 249kB).
- Live verification: real login → dashboard (chart lazy-loads and
  renders correctly, Recent Drafts capped) → a site with 8 posts
  (`/wordpress/1/posts`, all 8 rendering, no pagination controls since
  `last_page === 1`) → confirmed via Playwright against the restored
  (non-inflated) dev database, screenshots captured. Playwright
  installed with `--no-save` and uninstalled again afterward, per this
  project's established practice.
- Re-ran the original profiling script after the `WordPressPostMapper`
  fix, against the same inflated dataset, to confirm the query-count
  reduction empirically rather than trusting the code change alone
  (300 → 201 queries, matching the predicted 100-query reduction
  exactly).

## Deferred Work

- **Sites/Media index pagination** — same unbounded-query shape as
  Posts had; no measured problem yet at either's realistic scale.
  Revisit if either grows the way Posts did.
- **Redis caching** — evaluated and declined this milestone on measured
  evidence; revisit only if a future milestone's real usage data shows
  a specific expensive, repeated read.
- **Batching `WordPressPostMapper`'s create/update calls themselves**
  (not just the existence lookup) — the remaining 2 queries/item are
  real per-row work today; worth revisiting only if sync volume ever
  makes *that* a measured problem too.
- **Thumbnail/responsive-image generation** — named separately in
  `docs/adr/0010-media-platform.md`; a real performance concern for
  Media specifically, out of this milestone's measured scope (no
  profiled evidence it's currently a problem, and it's a different kind
  of fix — image processing, not query shape).
