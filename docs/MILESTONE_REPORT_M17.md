# Milestone 17 Report

## Date

2026-07-20

---

## Objective

Introduce Redis-backed caching where justified, optimize expensive
queries, improve frontend bundle/loading performance, and validate
performance under realistic datasets — continuing
`docs/adr/0005-domain-model.md`'s standing guidance against premature
optimization. Per the project's standing guidance since Milestone 15:
prefer the simpler of two approaches wherever it demonstrates the same
skill.

---

## Executive Summary

Milestone 17 is complete. Two ADRs (`0005-domain-model.md`,
`0008-content-synchronization.md`) had already named real performance
gaps and explicitly deferred them pending "a measured problem, not
before" — this milestone was that measurement, done first, against a
temporarily inflated dataset (34 sites, 6,012 posts, 2,756 analytics
snapshots), not a guess about what "should" be slow.

**Two real, quantified problems, fixed.** The Posts index returned
every matching row with no `LIMIT` (142ms for 6,012 rows at the
inflated scale) — now paginated (`page`/`per_page`, default 50, max
100, via an additive `meta.pagination` block). `WordPressPostMapper::
upsert()` ran one lookup query per WordPress item (300 queries for 100
items) — now batch-preloaded per page via a new `preloadExisting()`
method on the `ContentTypeMapper` contract, measured down to 201
queries for the same 100 items.

**One real, measured frontend win.** `/dashboard`'s First Load JS
(249kB) dwarfed every other route (103–188kB) — `recharts`, loaded
eagerly for a single chart widget. Code-split via `next/dynamic({ ssr:
false })`. Result: 144kB, a 42% reduction.

**Redis: a real decision, not a placeholder.** Evaluated actual
integration against the measured numbers above.
`DashboardService`'s aggregates (the most obvious caching candidate)
measured 5–12ms even at the inflated dataset's scale — already fast
enough that a cache layer's invalidation complexity wouldn't be worth
the saving, and neither real problem found was a caching problem in
the first place. Redis stays present-but-unused in Docker Compose,
exactly as Milestone 15 left it.

---

## Architecture Review

Read `docs/ROADMAP.md`'s Milestone 17 entry,
`docs/adr/0005-domain-model.md` (which named the Posts/Sites pagination
gap and the Dashboard-caching question, both "pending a measured
problem"), `docs/adr/0008-content-synchronization.md` (which named the
`WordPressPostMapper` N+1 in identical terms), and
`docs/adr/0010-media-platform.md`. All three had already done the hard
part — naming the candidate problems precisely — leaving this milestone
to actually measure them rather than start from scratch.

---

## Architecture Drift Review

No structural drift to evaluate — this milestone didn't introduce new
services, contracts, or infrastructure, only measured and fixed
specifically-named existing gaps. The one real architectural decision
was whether to integrate Redis at all: evaluated directly against
measured query timings (see Executive Summary and the ADR's Redis
section) and answered no, on evidence — the same kind of
evidence-before-architecture discipline this project's Docker (M15)
and CI (M16) milestones already applied to their own decisions.

---

## What Was Built

**Backend — Posts pagination**: `IndexPostsRequest` gained `page`/
`per_page` (`per_page` capped at 100, default 50). `PostController::
index()` now calls `->paginate()` instead of `->get()`, returning
`meta.pagination` (`current_page`/`per_page`/`total`/`last_page`) on
the existing `ApiResponse` envelope.

**Backend — sync N+1 fix**: `ContentTypeMapper::preloadExisting(Site,
array $wordpressIds): void` (new contract method).
`WordPressPostMapper` implements it — one `whereIn('wordpress_post_id',
...)` query, keyed by ID, into an instance-scoped cache;
`upsert()` reads from it instead of querying.
`ContentSyncService::sync()` calls it once per fetched page, before the
`foreach` loop.

**Frontend — pagination consumption**: `apiFetchWithMeta()` (new,
alongside the unchanged `apiFetch()`) in `api-client.ts`.
`posts.service.ts`'s `getSitePosts(siteId, page)` returns
`{ posts, pagination }`; `getRecentDrafts()` now requests
`per_page=5` instead of the full unbounded list. `useSitePosts` uses
`placeholderData: keepPreviousData` (TanStack Query v5) so paging
doesn't flash a loading state. `PostsTable` gained Previous/Next
controls, rendered only when `pagination.last_page > 1`.
`sitePostsQueryKey(siteId, page?)` — optional `page` so
`invalidateQueries` can still invalidate every page at once after a
sync.

**Frontend — dashboard code-split**: `analytics-preview-lazy.tsx` (new)
— a client wrapper around `next/dynamic(() => import(".../analytics-
preview"), { ssr: false, loading: ... })`, following the exact pattern
already used for `ReactQueryDevtools` in `query-provider.tsx`.
`dashboard/page.tsx` now imports the lazy wrapper instead of
`AnalyticsPreview` directly, and stays a Server Component (`ssr: false`
isn't permitted directly inside one, hence the wrapper).

**Documentation**: `docs/adr/0015-performance-and-scalability.md`, plus
amendments to `docs/PROJECT.md` (a new Milestone 17 section, five
Known Limitations bullets updated/resolved), `docs/ROADMAP.md`
(marked complete), `docs/DEVLOG.md`, and `docs/SESSION_HANDOFF.md`.

---

## Validation

- `php artisan test` — **142/142 passing**, unchanged by this
  milestone's changes.
- `./vendor/bin/pint --test` (full-repo) — clean.
- `npm run typecheck` / `npm run lint` — clean.
- `npm run test` — **20/20 passing**, unchanged.
- `npm run build` — clean; `/dashboard` First Load JS confirmed at
  144kB (down from 249kB) via the build's own route-size table.
- **Profiling methodology**: real query-count/timing measurement
  (`DB::listen()` + `microtime()`) against a temporarily inflated
  dataset, not synthetic assumptions. The dev database was backed up
  before inflating it and restored afterward — the shipped demo
  experience is untouched by the profiling data.
- **Re-measured after the fix, not just after the diff**: re-ran the
  exact same upsert-timing script post-fix and confirmed 300 → 201
  queries for 100 items — the predicted 100-query reduction, verified
  empirically rather than trusted from reading the code change alone.
- **Live browser verification** (Playwright, installed `--no-save` and
  uninstalled after): real login → dashboard (chart lazy-loads and
  renders correctly as an SVG, `Recent Drafts` capped at 5 items,
  matching the KPI card's own "3 draft posts" count) → a site with 0
  posts (correct empty state) → a site with 8 posts
  (`/wordpress/1/posts`, all 8 rendering, no Previous/Next controls
  since `last_page === 1`, as expected at demo scale) → confirmed
  against the restored, non-inflated dev database. No console errors
  on a warm server (one transient `SyntaxError` observed against a
  cold `php artisan serve` process's first request, gone on retry —
  a dev-server warm-up artifact, not an app defect; documented in
  `docs/SESSION_HANDOFF.md`).

---

## Self Review

Re-read every changed file with fresh eyes. `preloadExisting()`'s
cache is instance-scoped and overwritten per page (not accumulated) —
correct, since each page's WordPress IDs are disjoint from the last and
the mapper is resolved fresh per job execution, not bound as a
singleton (confirmed via a repo-wide search — no `singleton()` binding
exists for `ContentTypeMapper` or `WordPressPostMapper`). The
pagination `meta` addition is additive only — no existing consumer of
`data` breaks, confirmed by re-running every existing Pest test that
hits `/api/v1/posts` (all use post counts well under the new default
`per_page=50`, so none needed updating). `sitePostsQueryKey`'s optional-
`page` design was caught by `tsc`, not spotted proactively — a
pre-existing single-argument call site in `site-detail.tsx` (cache
invalidation after a sync) surfaced the need during typecheck, fixed
before it became a real bug rather than after.

---

## Production Readiness

The Posts pagination and N+1 fixes are real production-readiness
improvements — a workspace with hundreds or thousands of posts (this
project's own stated growth scenario) no longer means a slow,
unbounded index query or a sync that fires hundreds of sequential
lookup queries. The dashboard bundle reduction improves real
time-to-interactive for every dashboard visit, not just at scale. The
explicit decision *not* to add Redis is itself a production-readiness
signal in the other direction: no speculative infrastructure running
in production without a measured reason for it to exist.

---

## Technical Debt Resolved

- **Posts index unbounded query**, named since Milestone 7, reviewed
  and deliberately deferred again in Milestone 10.1 — resolved with
  real measured justification (142ms/6,012 rows).
- **`WordPressPostMapper::upsert()`'s N+1**, named and explicitly
  accepted in Milestone 8's own ADR pending "a measured problem" —
  resolved, with the measurement documented alongside the fix.
- **Dashboard bundle size**, not previously named as debt (no bundle-
  size measurement had been taken before this milestone's own build
  output surfaced it) — resolved.

---

## Deferred Work

- **Sites/Media index pagination** — identical unbounded-query shape
  to what Posts had; no measured problem yet at either's realistic
  scale (Sites: single digits to tens per workspace; Media: no real
  dataset to profile against today). Revisit if either grows the way
  Posts did.
- **Redis caching** — evaluated and explicitly declined this milestone
  on measured evidence, not silently skipped. Revisit only if a future
  milestone's real usage data shows a specific, repeated, expensive
  read.
- **Batching `WordPressPostMapper`'s create/update calls themselves**
  (only the existence lookup was batched) — the remaining 2
  queries/item are real per-row work; a further optimization only if
  sync volume ever makes *that* a measured problem too.
- **Thumbnail/responsive-image generation** — a real, separate Media
  performance concern named in `docs/adr/0010-media-platform.md`, out
  of this milestone's measured scope.

---

## Risks

- **The inflated profiling dataset was synthetic** (uniformly
  distributed posts/snapshots across 34 sites), not a real customer's
  actual data shape. The measured numbers are directionally reliable
  (query *count* scales with row count regardless of distribution) but
  a genuinely skewed real-world dataset (one enormous site among many
  tiny ones) could behave somewhat differently. Low risk — the fixes
  applied (pagination, batch preload) are correct regardless of
  distribution shape.
- **The Redis "not justified" decision is scale-dependent by
  construction** — it's the right call at today's and the profiled
  dataset's scale, not a permanent architectural position. Documented
  explicitly as revisitable, not as "Redis will never be needed."

---

## Recommendation for Milestone 18

Per `docs/ROADMAP.md`, Milestone 18 (Observability) is next —
structured logging, health checks, Sentry/OpenTelemetry integration,
request tracing, and operational metrics, using the integration points
established in earlier milestones. Waiting for explicit approval
before starting, per this milestone's own stop condition.
