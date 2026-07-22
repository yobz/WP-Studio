# 0010 — Media Platform & Storage

**Status:** Accepted (Milestone 12)

## Decision

Introduce a reusable Media domain — one `media` table, one `Media`
model, one `MediaService` — as the single source of truth for every
file this application stores, not a WordPress-sync-specific or
upload-specific mechanism. `Media` rows are polymorphically attachable
(`mediable_type`/`mediable_id` + `collection`) so any future producer
(WordPress featured images today; avatars, AI-generated images,
reports, attachments later) attaches to the same table without a
parallel schema. Extend the Content Synchronization Platform
(Milestone 10) so a post's WordPress featured image downloads
asynchronously via a new `DownloadMediaJob`, following the exact job
pattern Milestone 11 established. Continue using Laravel's Filesystem
abstraction exclusively (`Storage::disk(...)`) — no direct
`file_get_contents()`/`copy()`/`unlink()` in business logic anywhere in
this feature — behind a dedicated `MEDIA_DISK` config value, decoupled
from the app's generic `FILESYSTEM_DISK`, so a future move to S3/R2 is
a configuration change, not a code change.

## Context

**Where this sits in the project.** Milestone 10 fetched WordPress
posts but deliberately fetched no media — "no post body/content is
fetched or stored" (`docs/adr/0008-content-synchronization.md`), and
`featured_media` was never read from the WordPress REST response.
Milestone 11 built the job platform this milestone's async downloads
now use unmodified. This milestone closes the media gap both of those
named in passing, and is the first genuinely new domain (not an
extension of `Site`/`Post`) since `Workspace` in Milestone 7.

**Architecture Drift Review (new as of this milestone).** Reviewed the
full existing service/model/policy layer before writing any code — see
the Explore-agent research summarized at the top of this milestone's
own working notes. Findings: no duplicate services, no overlapping
responsibilities, no ADR violations. One naming collision risk
identified and resolved by inspection, not code change: `Site.storage_used_mb`/
`storage_limit_mb` (Milestone 6) describe the *remote WordPress site's*
own disk usage as reported by its REST API — unrelated to this
milestone's Media Platform, which manages WP Studio's own file
storage. No rename needed (the columns are correctly named for what
they represent), but worth stating explicitly so a future reader
doesn't conflate the two. `App\Services\WordPress\` was confirmed (via
`docs/AI_ENGINEERING_CONTEXT.md`) as the intended template for "a
future storage integration" — matched here: `MediaService` behind one
`config/media.php`, no separate HTTP client needed since Laravel's own
`Storage`/`Http` facades already are the abstraction layer.

**What already existed, unmodified.** `config/filesystems.php`'s
`public` disk (web-servable, `storage:link`-backed) and `s3` disk stub
have existed since Laravel's own defaults — Milestone 1. Neither
needed a code change; `MEDIA_DISK=public` is a new env value pointing
at the disk that already existed.

## Alternatives Considered

**`MediaDTO`, named explicitly in the brief, not built.** The brief's
own example layer list included `MediaDTO`. Every comparable resource
in this codebase (`Post`, `Site`) is an Eloquent model rendered
directly through an API Resource, with DTOs reserved for genuinely
ephemeral, non-model data (`SyncResultDTO`, `SyncStatisticsDTO`).
`Media` is a real, persisted Eloquent model with its own table —
introducing a DTO that mirrors its columns 1:1 would be an
unjustified extra layer with no data it doesn't already have.
Deliberately followed the `Post`/`Site` precedent instead of the
brief's example list literally, the same category of judgment call
Milestone 11 made about not wiring `RefreshSiteMetadataJob` into the
manual button.

**Polymorphic attachment (`mediable_type`/`mediable_id` + `collection`)
vs. a single `featured_media_id` FK on `posts`.** A dedicated
`posts.featured_media_id` column is simpler for the one relationship
that exists today. Rejected: it doesn't generalize — every future
producer the brief names (avatars, AI images, attachments, reports)
would need its own FK column on its own table, defeating "the single
source of truth for every file." The polymorphic shape costs one
extra join today and pays for every future attachment point without a
schema change.

**DB-level uniqueness on `(mediable_type, mediable_id, collection)`
and `(site_id, source_id)` — considered, built, then removed.** Both
were added as genuine unique constraints during implementation and
caught replacing a post's featured image failing with a real
`QueryException`: soft-deleting the old attachment row and inserting
a new one for the same post violated the constraint, because
`SoftDeletes` makes a row logically absent but physically still
present — the unique index has no concept of `deleted_at`. `posts`
already carries this exact same tradeoff on its own
`(site_id, wordpress_post_id)` unique index, coexisting with its own
`SoftDeletes`, apparently never exercised the same way. Rather than
propagate a known-fragile pattern silently, both constraints became
plain (non-unique) indexes — the "one attachment per slot" and
"don't re-download the same source" invariants are enforced in
`WordPressPostMapper::syncFeaturedImage()` and `DownloadMediaJob`
instead, which is where the actual business decision already lived.

**File-content deduplication: reuse the DB row vs. reuse only the
stored bytes.** Considered making a second attachment to an
already-stored file return the *same* `Media` row (true row-level
dedup). Rejected for this milestone: the polymorphic design ties one
`Media` row to at most one `mediable`/`collection` slot at a time, so
sharing a row across two attachments would mean one row with two
owners — a real design question (does deleting one attachment delete
the shared file for the other?) with no product need behind it yet.
Chosen instead: `MediaService` hashes content before writing and
reuses the existing `storage_path` (skips the disk write) whenever a
matching hash already exists in the workspace, but always creates a
new `Media` metadata row per attachment. Satisfies "do not duplicate
files unnecessarily" (the brief's literal requirement) without
inventing shared-ownership semantics nothing asks for yet.

**MIME allow-list scoped to images only, not the brief's broader
"Reports"/"Attachments" examples.** The brief names Reports and
Attachments as future Media Platform consumers. Building document
upload support (PDFs, etc.) now would be speculative — no controller
or feature in this milestone needs it. `config('media.allowed_mimes')`
is a single array a future milestone extends; the validation, storage,
and dedup logic are already format-agnostic.

**Virus scanning — reviewed, explicitly deferred, twice now.** No
scanning exists in this environment (no ClamAV or equivalent service
configured anywhere in this project). **Re-evaluated, Milestone 19:
still not implemented as code, deliberately** — no real scanning
service exists to build or test against without live infrastructure
that milestone's "deployment-ready, not deployed" scope excluded.
Documented as a concrete recommendation (a ClamAV sidecar, or the
object-storage provider's own scanning add-on) in
`docs/DEPLOYMENT.md`, not silently assumed safe. See
`docs/adr/0017-cloud-deployment-and-security-hardening.md`.

## Media Schema

```
media
├── id, workspace_id (FK), site_id (FK, nullable), uploaded_by (FK, nullable)
├── mediable_type, mediable_id (nullable — polymorphic attachment)
├── collection (nullable — e.g. "featured_image")
├── source ("upload" | "wordpress"; "ai_generated" reserved, unused)
├── source_id (nullable — external ref, e.g. the WordPress media ID)
├── disk, storage_path, original_url (nullable)
├── filename, extension, mime_type, size, width, height, hash (sha256)
├── alt_text
└── timestamps, soft-deletes
```

`workspace_id` is required on every row — tenant scoping lives on
`Media` directly, not derived transitively through `mediable`, since
`mediable` is nullable (an unattached library upload has no parent to
derive a workspace from). `hash` (sha256, indexed with `workspace_id`)
is the storage-dedup key; `storage_path` never derives from a
user-supplied filename — every write uses `media/{workspace_id}/{uuid}.{ext}`,
so path traversal via a crafted filename isn't a code path that
exists (the original filename is preserved only in the `filename`
metadata column, for display).

## Storage

`config/media.php` — `disk` (default `public`, `MEDIA_DISK` env,
independent of `FILESYSTEM_DISK`), `max_upload_kb` (default 10 MB),
`allowed_mimes` (`jpg`, `jpeg`, `png`, `gif`, `webp` — `svg`
deliberately excluded, see Security). `MediaService` is the only class
that calls `Storage::disk(...)->put()`; every controller and job goes
through it. Moving to S3/R2/Spaces requires setting `MEDIA_DISK=s3`
and the existing `AWS_*` env vars (already stubbed in
`config/filesystems.php` since Milestone 1) — zero code changes, the
property this ADR's Decision section commits to.

## WordPress Integration

```
Before (Milestone 10):  WordPress → Posts → Database  (no media)
After (Milestone 12):   WordPress → Posts → Featured Image → Media Platform → Database
```

`WordPressPostMapper::map()` now reads `featured_media` from the raw
WordPress post payload and includes it in the post's change-detection
hash (a featured-image change alone now correctly produces an
`Updated` sync outcome, not a false `Skipped`).
`WordPressPostMapper::upsert()` — already the WordPress-post-specific
logic, not the generic `ContentSyncService` orchestrator — gained
`syncFeaturedImage()`: no-ops if the post already has the same
WordPress media ID attached (the "do not duplicate downloads"
guarantee, verified directly against a real re-sync in this
milestone's tests, not assumed), soft-deletes the old attachment and
dispatches `DownloadMediaJob` if the ID changed, or soft-deletes with
no download if WordPress reports no featured image at all — this last
case is synchronous and IO-free (a delete, not a fetch), so it doesn't
need a job.

`DownloadMediaJob` follows Milestone 11's exact job shape: `$tries =
3`, `backoff() = [10, 30, 60]`, `uniqueId()` keyed per-`Post`,
`SerializesModels`. `WordPressClientContract` gained one new method,
`fetchItem()` (a single-resource GET, reusing the existing
`fetchRequired()` internals `HttpWordPressClient` already had) —
generic enough to serve any future single-item WordPress fetch, not
media-specific. The job fetches the WordPress media item's detail
(`source_url`, `mime_type`, `media_details.width/height`, `alt_text`),
downloads the bytes via `Http::get()` (never a raw filesystem call),
and delegates storage to `MediaService`.

## Queue Integration

Only `DownloadMediaJob` was built. `GenerateThumbnailJob`/
`OptimizeImageJob`, named as examples in the brief, were not — nothing
in this milestone's scope needs a thumbnail or a re-encoded variant;
building either now would be process, not product, work. Width/height
extraction happens synchronously and cheaply: WordPress-sourced media
gets it for free from the REST API's own `media_details` field (no
image parsing needed); direct uploads use PHP's core
`getimagesizefromstring()` (no GD/Imagick dependency, works on raw
bytes).

## Security

- **MIME/extension validation is a real allow-list, not just an
  extension check.** `StoreMediaRequest` uses Laravel's `mimes:` rule
  (inspects actual file content via Symfony's Mime component, not
  just the client-supplied extension) against `config('media.allowed_mimes')`.
- **`svg` is deliberately excluded** from the allow-list — an SVG can
  embed inline `<script>`, a real stored-XSS vector if ever served
  with an `image/svg+xml` content type and rendered directly. Not
  "forgotten"; a documented, deliberate omission.
- **Filenames never reach the storage path.** Every stored path is
  `media/{workspace_id}/{uuid}.{extension}` — the user-supplied
  filename is preserved only as display metadata (`filename` column),
  never interpolated into a filesystem path, closing path traversal as
  a code path entirely rather than sanitizing an untrusted string.
- **The WordPress media download reuses the existing SSRF guard.**
  `UrlSafetyValidator::assertSafe()` (Milestone 9) runs against the
  media's `source_url` before `DownloadMediaJob` fetches it — the same
  check already protecting the WordPress connection handshake, applied
  here since a WordPress site's REST API response is exactly the kind
  of "URL from an external source we don't control" this validator
  exists for.
- **Tenant isolation.** Every `Media` row carries `workspace_id`
  directly (not derived transitively); `MediaPolicy` checks
  `$media->workspace->hasMember($user)`/`roleFor($user)`, identical in
  shape to `SitePolicy`/`PostPolicy`. `MediaController::index()` scopes
  its query to the resolved `CurrentWorkspaceContext` workspace, the
  same pattern `PostController::index()` already established — never
  trusting a client-supplied workspace ID.
- **Upload rate limiting.** A dedicated `media-upload` rate limiter
  (20/minute per user), the same category of protection
  `wordpress-connection` (10/minute) already applies to WordPress
  handshakes.
- **Virus scanning: explicitly deferred.** See Alternatives Considered.

## Performance

- **No duplicate downloads.** `syncFeaturedImage()`'s guard (same
  WordPress media ID already attached → no-op) means an unchanged
  post's re-sync never re-fetches its image — verified directly by a
  dedicated test, not assumed from the outer sync-hash mechanism alone
  (a post whose *other* fields changed but featured image didn't would
  otherwise still trigger a redundant re-download without this
  specific guard).
- **No duplicate file writes.** `MediaService`'s hash-based dedup
  reuses an existing `storage_path` instead of writing identical bytes
  to disk twice, for both direct uploads and WordPress downloads.
- **Eager loading.** `PostController` now eager-loads `featuredImage`
  everywhere `PostResource` is returned (`index`, `show`, `update`) —
  no N+1 across a posts listing.
- **Named, accepted limit: no thumbnail generation.** Every rendered
  image (grid, list, preview, post detail) serves the original
  upload/download at full resolution — no responsive `srcset`, no
  server-generated thumbnail sizes. Acceptable at this milestone's
  scale (single images, not a high-volume media library yet). **Update,
  Milestone 17:** named as this platform's next consumer, but not
  picked up — that milestone's actual measured hot paths were Posts
  pagination and the WordPress sync N+1
  (`docs/adr/0015-performance-and-scalability.md`), not media
  rendering; no evidence made thumbnail generation the higher-value
  fix at the time. Remains real, open future work.

## Frontend

`src/features/media/` — `MediaLibrary` (page-level: grid/list toggle,
upload, empty/loading/error states), `MediaGrid`/`MediaList` (two
independent renderings of the same `ApiMedia[]`), `MediaPreviewDialog`
(image, metadata, an alt-text edit form, delete), `MediaUploadButton`
(hidden file input + visible trigger, following the same
error-display pattern `ConnectSiteDialog` established). `apiUpload()`
(`src/lib/api-client.ts`) is a new sibling to `apiFetch()` sharing its
envelope-parsing logic, differing only in omitting the JSON
`Content-Type` header so the browser sets the multipart boundary
itself for `FormData` bodies — the one genuinely new client-side
primitive this milestone needed. `PostResource`/`ApiPost` gained
`featured_image`; `PostsTable` and `PostDetail` render it when present,
with no change to either component's loading/error/empty states.

**A real, novel accessibility finding, fixed during self-review.**
Placing a `variant="destructive"` `Button` inside a `DialogFooter`
(which has its own semi-transparent `bg-muted/50` background) failed
WCAG AA contrast (4.24:1 against the 4.5:1 threshold) — a combination
that doesn't exist anywhere else in this app (the two other
`destructive`-variant buttons sit on plain page backgrounds, which
pass). Fixed by moving Delete out of the footer into the dialog body,
next to the metadata caption, rather than overriding the shared
`Button` component's color tokens for one instance.

## Rejected Alternatives

**A generic polymorphic content table, extended to cover media too.**
Considered folding `Media` into some broader "Assets" abstraction
spanning multiple content types. Rejected for the same reason
`docs/adr/0008-content-synchronization.md` rejected a generic
polymorphic content table for Posts/Pages: guessing a shared shape
before a second real content type exists produces a worse schema than
waiting. `Media` is scoped, concrete, and already reusable via its own
polymorphic attachment columns — a second, unrelated polymorphism
layered on top would be speculative.

**A `media_mediable` many-to-many pivot for shared attachments.**
Considered upfront to let one physical file back multiple attachments
with independent lifecycles. Rejected as premature — no current
feature attaches the same image to two different posts/entities
simultaneously; the simpler one-row-per-attachment model (with
storage-level, not row-level, dedup) covers every real case this
milestone has. A real candidate if a future feature needs it.

## Future Evolution

- **Milestone 14 (AI-Assisted Content Generation):** `source =
  'ai_generated'` is already a reserved, unused value in the schema —
  an AI image generation job attaches its output the same way
  `DownloadMediaJob` does today, no schema change.
- **Thumbnail/responsive-image generation** (a natural
  `GenerateThumbnailJob`/`OptimizeImageJob`) and CDN-backed serving
  remain named, real future work — not picked up by Milestone 17
  (Performance & Scalability), whose actual measured hot paths lay
  elsewhere; see the Performance section above.
- ~~**Milestone 19 (Cloud Deployment & Security Hardening):**
  `MEDIA_DISK=s3` (or R2/Spaces) is the entire migration — no code
  change, per this ADR's own Decision.~~ **Confirmed, Milestone 19** —
  the claim held exactly as predicted; Cloudflare R2 chosen, zero code
  touched, env vars documented in `docs/DEPLOYMENT.md` §3. Virus
  scanning remains real, deferred work — see that ADR's own Virus
  Scanning section.
- **Avatars, Attachments, Reports** (named in the brief as future
  producers) each attach via `mediable_type`/`mediable_id` +
  a new `collection` value — no new table, no new service.
