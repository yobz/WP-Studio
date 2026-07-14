# Milestone 10 Report

## Date

2026-07-14

---

## Objective

Transform WP Studio from "Site Management" into "Content Management" —
the platform's first read-back from a connected WordPress site. Users
should be able to browse WordPress posts, synchronize them,
inspect metadata, refresh content, and view sync status, without
modifying WordPress content yet (Publishing is future scope).
Synchronization must be idempotent, must not duplicate imports, and
the underlying architecture must generalize to future content types
(Pages, Media, Categories, Tags, Custom Post Types) without hardcoding
"Posts."

This milestone's slot in `docs/ROADMAP.md` previously read "API
Completion & Frontend Migration." The brief explicitly redefined it as
Content Synchronization instead; the displaced original scope is
preserved as Milestone 10.1, not dropped.

---

## Executive Summary

Milestone 10 is complete and, on independent review, sound. The
architecture review surfaced the milestone's real central question
before any code was written: `Post` has existed since Milestone 7 with
full CRUD but zero frontend consumers, and the brief's instruction to
sync into "the posts table" left implicit whether a synced post and a
manually-created one are the same kind of thing. Deciding to extend
the existing table (not build a parallel one) required tracing every
existing and future consumer of `Post` — the correct amount of rigor
for a decision that's expensive to reverse once shipped.

The generic-sync-engine requirement was satisfied by putting the
genericity in the *orchestrator* (`ContentSyncService` depends only on
a small `ContentTypeMapper` contract, never on "posts" by name), not
by guessing a polymorphic schema for content types that don't exist
yet — the identical discipline `docs/adr/0005-domain-model.md` already
established and named for deferring the "AI Jobs" table. This is the
correct generalization: the *process* (fetch → map → hash → upsert →
report) is genuinely reusable across content types today; the *data
shape* of Pages or Media isn't knowable without a second real case to
validate against, so it wasn't guessed at.

Idempotency is real, not asserted: a sha256 hash of each item's
mapped, change-relevant fields — not a bare `wordpress_modified_at`
comparison — gates every write decision, and a unique
`(site_id, wordpress_post_id)` index is the actual duplicate-import
guard. Both are proven by tests that would fail under a naive
implementation: re-syncing identical fixture data twice produces zero
new rows on the second call; changing one field produces exactly one
`updated` row, not a duplicate.

One real test-authoring bug was found and fixed during this milestone
(see Engineering Journal, 2026-07-14): `Http::fake()` called a second
time mid-test to change mocked responses doesn't override the first
call's rule for the same URL pattern — Laravel resolves by
first-registered match, not last. Caught by the test itself failing in
a way that revealed the actual mechanism, not by inspection.

Backend test coverage grew from 73 to 83 tests, all mocking WordPress
via `Http::fake()` — zero live external dependency. The feature's
failure path was additionally verified live in a production-build
browser against the seeded environment's genuinely unreachable
`acmeblog.example.com` domain — a real `WordPressConnectionException`
correctly flipped the site to `Connection Error` and rendered through
the same error-display path Milestone 9's verify/refresh actions
already established.

---

## Engineering Summary

**Backend.** `App\Services\ContentSync\` — `ContentSyncService` (generic
orchestrator: credential/URL-safety checks, paginated fetch, per-item
map/hash/upsert, result tallying, `ContentSynced` event dispatch, site
error-state handling on total failure), `Contracts\ContentTypeMapper`
(the extensibility seam), `Mappers\WordPressPostMapper` (the one
concrete implementation — endpoint, trash-skipping, WordPress-status →
`PostStatus` mapping, hash computation, `Post` upsert, sync-count
query), `DTO\{MappedContent,SyncResultDTO,SyncStatisticsDTO}`,
`Enums\SyncOutcome`, `Exceptions\ContentSyncException` (extends the
existing `ApiException`). `WordPressClientContract` gained one new
method, `fetchCollection()`, implemented in `HttpWordPressClient` by
reusing its existing request/retry/timeout/auth machinery (a shared
`assertSuccessfulJsonArray()` helper now backs both `fetchRequired()`
and `fetchCollection()`, avoiding duplicated response-validation
logic). New `ContentSynced` event + `LogContentSynced` listener,
mirroring `SiteConnected`/`LogSiteConnected`. New migration: `posts`
gained `wordpress_post_id` (nullable, unique per `site_id`),
`wordpress_modified_at`, `wordpress_url`, `sync_status`, `sync_hash`,
`last_synced_at`; `sites` gained `last_synced_at`. New
`ContentSyncController` (`sync`, `syncStatus`), both delegating only to
`ContentSyncService` — never to `WordPressClientContract` directly.
`SyncResultResource`/`SyncStatisticsResource` render the two DTOs into
the existing snake_case envelope convention, the same pattern
`DashboardSummaryResource` already established. `PostResource`/
`SiteResource` extended with the new fields.

**Frontend.** `src/services/api/posts.service.ts` (new — `Post`'s first
frontend consumer); `syncSite()`/`getSyncStatus()` added to the
existing `sites.service.ts`. Four new hooks
(`use-site-posts`, `use-post`, `use-sync-site`, `use-sync-status`) and
four new components (`PostsTable`, `PostDetail`, `SyncButton`,
`SyncSummary`), all composing existing primitives only. Two new nested
routes, `/wordpress/[id]/posts` and `/wordpress/[id]/posts/[postId]` —
this app's second level of route nesting. `SiteDetail` extended with a
"View Posts" link, a `SyncButton`, and a `SyncSummary` card, reusing
its existing action-row/error-display conventions rather than
inventing new ones.

---

## Security Summary

- **Every existing layer carries over unchanged.** `auth:sanctum` +
  `ResolveCurrentWorkspace` scope both new routes to the caller's
  workspace; `SitePolicy::view` gates both (matching Milestone 9's own
  classification of `verifyConnection`/`refreshMetadata` as
  read-adjacent, not requiring owner/admin); `UrlSafetyValidator` runs
  before any outbound request, identical to every other WordPress-
  facing action; the existing `wordpress-connection` rate limiter
  (10/minute) covers `POST /sites/{site}/sync`.
- **Credentials are read, never duplicated.** `ContentSyncService`
  reads `Site::credential` through the existing encrypted relation —
  no new credential storage or handling path was introduced.
- **No new client-settable fields.** Every sync-tracking column on
  `posts` (`wordpress_post_id`, `sync_hash`, etc.) is server-derived
  only, written exclusively by `WordPressPostMapper::upsert()` — never
  accepted from a request body. Manually-created posts via the
  existing `PostController::store()` continue to go through
  `StorePostRequest`'s unchanged validation, untouched by this
  milestone.
- **Tenant isolation verified directly.** A dedicated test
  (`cannot sync a site in another workspace`) and a role test (`lets
  any workspace member trigger a sync, not just owners/admins`) both
  pass, matching the isolation discipline every prior milestone since
  M8 has established.

---

## Architecture Summary

Independently re-assessed against the milestone's own two hardest
requirements: **(1) generalize without hardcoding "Posts,"** and **(2)
extend, don't replace, existing architecture.**

On (1): the genericity is real, not aspirational — `ContentSyncService`
contains zero references to `Post`, `posts`, or WordPress's `/wp/v2/posts`
endpoint anywhere in its own source; every content-type-specific detail
lives in `WordPressPostMapper`, reached only through the
`ContentTypeMapper` interface. A future Pages sync is, by construction,
a new mapper class and whatever local table Pages actually needs — not
a hypothetical claim, a direct consequence of the dependency direction
chosen.

On (2): every extension point was traced against what already existed
rather than assumed. `posts` was extended, not forked, after tracing
every current and foreseeable consumer. `PostController::index` (built
Milestone 7) was reused for reads instead of adding a duplicate nested
route — a real, checked decision (its existing `site_id` filter and
workspace scoping were read directly, not assumed present).
`WordPressClientContract` gained a second method rather than a second
client class, reusing `HttpWordPressClient`'s auth/retry/timeout
machinery outright. The one place this milestone deliberately diverges
from a *literal* reading of the brief — not adding the example nested
`GET /sites/{site}/posts` route — is documented with its reasoning in
both the ADR and this report, not silently substituted.

The one place worth naming as a real, accepted trade-off rather than
an oversight: `WordPressPostMapper::upsert()` performs one lookup query
per WordPress item rather than a batch upsert. This was scrutinized
during self-review specifically for whether it was a correctness risk
(it isn't — each lookup is a correct, indexed query) or a latent
performance problem (it is, at a scale this milestone's real usage
doesn't reach) — see Technical Debt below.

---

## Accessibility Summary

Not independently re-audited with `axe-core` this milestone — no new
design-system primitive was introduced (all four new components
compose `Card`, `StatusBadge`, `LoadingState`, `EmptyState`,
`PageHeader`, all already audited in prior milestones), and the new
routes follow the identical landmark/heading structure
`/wordpress/[id]` already established in Milestone 9. The Milestone 8
`region`-rule finding for portaled `DropdownMenu`/`Popover` content
remains unchanged and tracked; this milestone introduces no new
portaled content. Recommend folding a dedicated `axe-core` pass on
`/wordpress/[id]/posts` and `/wordpress/[id]/posts/[id]` into whichever
milestone next does a broader accessibility sweep, since it hasn't
been done for these two routes specifically yet.

---

## Technical Debt

New this milestone (see `docs/ENGINEERING_JOURNAL.md`'s Future
Backlog for full entries):

- `WordPressPostMapper::upsert()` runs one lookup query per WordPress
  item, not a batch operation — Medium, not yet a measured problem at
  real usage.
- Content sync is fully synchronous, bounded to 20 pages (2,000 posts)
  per call as a safety cap — Medium, named as the exact seam Milestone
  11 removes.
- Content sync fetches only post metadata, no post body/content —
  Medium, deliberately deferred until an editing/Publishing feature
  actually needs it.
- The Posts list (`/wordpress/[id]/posts`) inherits the pre-existing
  "no pagination on Sites/Posts index endpoints" gap (Milestone 7) —
  no longer theoretical now that a real UI renders `Post` rows.

Resolved this milestone:

- None of the pre-existing Future Backlog items were resolved by this
  milestone — it primarily added new, named debt of its own rather
  than closing existing items, which is consistent with this being a
  net-new feature milestone rather than a hardening one.

---

## Production Engineering Review

| Layer | What changed | What was deferred | Future considerations |
| --- | --- | --- | --- |
| Frontend | New `/wordpress/[id]/posts` and `/wordpress/[id]/posts/[postId]` (second route-nesting level), four new components, `Post`'s first frontend consumer | Post pagination/filtering in the UI | Revisit once Posts list pagination (backend) exists |
| Backend/API | New `ContentSyncController`, extended `WordPressClientContract`, `PostResource`/`SiteResource` | Bulk/multi-site sync trigger | — |
| Database | `posts` sync-tracking columns + unique index, `sites.last_synced_at` | Post body/content storage | Add when Publishing needs to render/edit content |
| Authentication | Unchanged | — | — |
| Authorization | New actions wired to existing `SitePolicy::view`, unchanged logic | — | — |
| External Integrations | Second WordPress REST operation (paginated collection fetch) added to the existing client | Real version/PHP-version detection (unchanged from M9) | — |
| Security | Full reuse of existing SSRF guard, rate limiter, tenant isolation | — | — |
| Performance | Bounded synchronous pagination (20 pages/call), per-item upsert queries | Batch upsert, background/async sync | Milestone 11 removes the synchronous bound; batch upsert revisit at real scale |
| Observability | Existing `AssignRequestId`/`ApiExceptionHandler` cover new routes automatically; new `ContentSynced`/`LogContentSynced` domain event | Structured per-item sync failure logging beyond `SyncResultDTO.errors` | — |
| Logging | One new log line per completed sync (`LogContentSynced`) | — | — |
| Scalability | No change at today's scale | Per-item query volume at real post counts | Revisit once real sync volume exists |
| Developer Experience | New `fakeWordPressPostsCollection()` and siblings in `tests/Pest.php` — reusable WordPress-posts-collection fake helpers for any future test | — | — |

---

## Validation

- `npm run typecheck`: pass.
- `npm run lint`: pass.
- `npm run build`: pass — 13 routes, `/wordpress/[id]/posts` (4.3 kB)
  and `/wordpress/[id]/posts/[postId]` (4.31 kB, both server-rendered
  on demand) both new.
- `php artisan test`: **83/83 passing** (up from 73) — 10 new
  (`ContentSyncTest`), all others unchanged.
- `./vendor/bin/pint --test` on every new/modified file this milestone
  touched: pass (pre-existing, unrelated style debt on untouched files
  elsewhere in the codebase left as-is, not part of this milestone's
  scope).
- End-to-end browser verification (production build): login, sidebar
  navigation to a connected site, the new "View Posts"/"Sync Content"
  controls and `SyncSummary` card rendering correctly, the Posts list
  correctly reusing `GET /posts?site_id=` to show 8 seeded posts
  (proving the reuse-over-duplication decision holds in practice, not
  just in the route table), a post detail page rendering correctly for
  a manually-created (non-synced) post, and a **real** sync-failure
  attempt against the seeded environment's genuinely unreachable
  `acmeblog.example.com` domain — correctly producing a
  `WordPressConnectionException`, flipping the site to `Connection
  Error`, and rendering the error through the existing error-display
  path. Zero unexpected console errors.
- The success path (a real WordPress site returning real post data)
  could not be verified live in-browser — no real WordPress server
  exists in this environment, the same constraint noted since
  Milestone 9. Covered instead by 9 of the 10 new backend tests, all
  mocking WordPress responses via `Http::fake()`.
- No existing routes, API contracts, or database schema broken — every
  new `posts`/`sites` column is additive and nullable/defaulted; no
  existing migration was amended.

---

## Self Review

**Architectural issues found:** none blocking. The decision to extend
`posts` rather than fork it, and to put genericity in the engine
rather than the schema, were both scrutinized specifically for
premature-abstraction risk in the opposite direction (was a
polymorphic content table actually warranted here?) and concluded no,
consistent with this project's established "AI Jobs" precedent for
deferring schema guesses.

**Testing gap found and closed during self-review:** the initial test
suite covered synchronization, idempotency, update detection,
trash-skipping, mapper correctness, credential-required, workspace
isolation, and sync-status — but not the "any workspace member, not
just owner/admin, can trigger sync" case Milestone 9 established as a
standing pattern for read-adjacent actions. Added directly
(`lets any workspace member trigger a sync, not just owners/admins`),
bringing the new suite to 10 tests / 83 total.

**A real test-authoring bug found and fixed:** `Http::fake()`'s
first-match-wins behavior across repeated calls (see Engineering
Journal, 2026-07-14) — caught by the update-detection test itself
failing in a way that revealed the actual mechanism, fixed with
`Http::sequence()`, and the now-orphaned single-response fixture
helper was deleted rather than left as dead code.

**Maintainability risks:** low. The `ContentSync` layer is five files
plus one mapper, small enough to read in full in one sitting; the
`ContentTypeMapper` contract gives a future contributor adding a
second content type an obvious, narrow seam to implement against
rather than a monolith to untangle or reverse-engineer from
`WordPressPostMapper`'s internals.

**Performance risks:** one real, named trade-off (per-item upsert
queries), not a correctness risk — see Technical Debt. Bounded
pagination means a worst-case sync call costs at most 20 sequential
paginated requests, a real, bounded number rather than unbounded
behavior against an arbitrarily large WordPress site.

**Production readiness:** the sync feature is genuinely
production-shaped for its stated scope (real idempotency, real
tenant isolation, real SSRF/rate-limit reuse, a real and tested
failure path) — the gaps that remain (per-item query volume, no post
body storage, synchronous-only) are named, bounded, and don't block
the feature from being real and safe to use today, only from being
maximally complete or scaled beyond today's real usage.

---

## Final Verdict

**Approved.** The architecture review's central payoff this
milestone — surfacing and deciding the `Post` table-collision question
before any migration was written, rather than discovering it mid-
implementation or leaving it ambiguous — is exactly what that stage of
the lifecycle exists for. The genericity requirement was satisfied
correctly (in the engine, not the schema), matching this project's own
established precedent rather than reinventing the reasoning. One real
bug was found and fixed by the test suite doing its job during
development, and one real test-coverage gap was found and closed
during self-review before this report was written. No blocking issues.
Ready to commit.

Recommended next steps: commit this milestone's work, then begin
Milestone 10.1 (API Completion & Frontend Migration — the scope this
milestone's slot originally held) or Milestone 11 (Background Jobs &
Queues, the direct continuation of this milestone's own synchronous-
to-async seam) per `docs/ROADMAP.md` — only after explicit approval,
per this milestone's own stop condition.
