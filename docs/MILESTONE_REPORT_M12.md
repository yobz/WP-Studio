# Milestone 12 Report

## Date

2026-07-15

---

## Objective

Establish the application's reusable Media Platform — the single
source of truth for every file the application stores, from WordPress
featured images to user uploads to (later) AI-generated images,
avatars, attachments, and reports. Not simply "add file upload": build
a production-ready media architecture every current and future
producer consumes, rather than each inventing its own storage
implementation.

---

## Executive Summary

Milestone 12 is complete and, on independent review, sound. The
repository review and this milestone's new mandatory Architecture
Drift Review both confirmed a clean, genuinely greenfield starting
point — no prior `Media`/`Attachment`/`Upload` code existed anywhere
in the codebase, and no drift (duplicate services, overlapping
responsibilities, ADR violations) was found in the surrounding
architecture. One naming-adjacent risk was identified and resolved by
documentation rather than code change: `Site.storage_used_mb`/
`storage_limit_mb` (Milestone 6) describe the *remote WordPress site's*
disk usage, unrelated to this milestone's own storage concern — now
explicitly disambiguated in ADR 0010 so a future reader doesn't
conflate the two.

The actual work was a new domain: a polymorphically-attachable `Media`
model (workspace-scoped, hash-deduplicated, disk-abstracted) that
Milestone 10's Content Synchronization Platform now feeds through a
new `DownloadMediaJob`, built to the exact job pattern Milestone 11
established (retries, backoff, per-resource uniqueness, `SerializesModels`).
A real, self-caught defect shaped the final schema: a DB-level unique
constraint on the polymorphic attachment slot, added during
implementation, broke replacing a post's featured image because
`SoftDeletes` makes a row logically-but-not-physically absent — caught
by this milestone's own test suite before it shipped, and fixed by
moving that invariant into the service layer (documented in ADR 0010's
Alternatives Considered, alongside the discovery that `posts` already
carries the identical, apparently-unexercised tradeoff).

One deliberate scope decision, following Milestone 11's precedent of
exercising judgment rather than following the brief's example list
literally: no `MediaDTO` was built, since `Media` is a real Eloquent
model rendered through an API Resource — the same shape `Post`/`Site`
already use — and a DTO mirroring its columns 1:1 would be an
unjustified extra layer.

A genuine, novel accessibility defect was found and fixed during
self-review, not merely audited after the fact: a destructive-variant
button placed inside a dialog's muted footer background failed WCAG
AA contrast, a combination this app had never used before (its two
other destructive buttons sit on plain backgrounds, which pass). Fixed
by relocating the button, not by overriding shared design-system
tokens for one instance.

---

## Architecture Review

Read `docs/AI_ENGINEERING_CONTEXT.md`, `docs/CODING_STANDARDS.md`,
`docs/prompts/milestone-lifecycle.md`, ADRs 0005–0009, and Milestone
9–11 reports before writing code (via a dedicated research pass, then
direct inspection of the actual current code for every pattern this
milestone needed to extend: Service Layer/DTO/Policy/Resource/Form
Request shape, `CurrentWorkspaceContext` usage, the M11 job pattern,
`WordPressPostMapper`'s exact mapping/upsert flow, `config/filesystems.php`,
and the full existing migration/schema-naming conventions). Confirmed
`App\Services\WordPress\` is the codebase's own named template for "a
future storage integration" (`docs/AI_ENGINEERING_CONTEXT.md`) — this
milestone followed that shape: one service behind one config file, no
unnecessary contract/interface layer, since Laravel's own `Storage`/
`Http` facades already are the abstraction this feature needs.

---

## Architecture Drift Review

**No duplicate services, abstractions, or overlapping
responsibilities found.** A targeted search confirmed zero pre-existing
`Media`/`Attachment`/`Upload` table, model, migration, controller,
service, job, or route anywhere in the repository — this domain was
genuinely greenfield.

**One naming-adjacent risk, resolved by documentation.**
`Site.storage_used_mb`/`storage_limit_mb` (Milestone 6) could be
mistaken for this milestone's own storage-usage concern. They describe
the remote WordPress site's disk usage as reported by its own REST
API — entirely unrelated to WP Studio's own file storage. No rename
was needed (both columns are correctly named for what they represent);
ADR 0010 states the distinction explicitly so it isn't rediscovered
later.

**Existing architectural decisions still hold.** The Service Layer
(no repository layer), Policy-per-model authorization,
`CurrentWorkspaceContext` for tenant scoping, Form Requests for
validation, and API Resources for response shaping were all extended,
not replaced — `MediaService`, `MediaPolicy`, `MediaController`,
`StoreMediaRequest`/`IndexMediaRequest`/`UpdateMediaRequest`, and
`MediaResource` each follow their `Post`/`Site` equivalents' exact
shape. The Milestone 11 job platform (`ShouldQueue`/`ShouldBeUnique`/
`SerializesModels`, `$tries = 3`, `backoff() = [10, 30, 60]`,
per-resource `uniqueId()`) was reused for `DownloadMediaJob` without
any change to its own conventions.

**One real drift caught and resolved before shipping, not after.**
See Executive Summary and ADR 0010's Alternatives Considered — a
DB-level unique constraint that actively broke a legitimate workflow
was removed in favor of service-layer enforcement, and the equivalent,
apparently-dormant risk already present on `posts`' own schema was
identified and documented rather than silently left for a future
session to rediscover.

---

## Media Platform Design

`App\Models\Media` — polymorphically attachable (`mediable_type`/
`mediable_id` + `collection`), workspace-scoped directly (not derived
transitively through `mediable`, since library uploads may have no
parent), `source` (`upload` | `wordpress`, `ai_generated` reserved),
`hash` (sha256) for storage dedup, `SoftDeletes` matching `Post`/
`Site`'s own convention. `Post::featuredImage()` is a `morphOne`
scoped to `collection = 'featured_image'`. `MediaService` is the only
class that writes to `Storage`; `MediaPolicy`/`MediaController`/
`MediaResource`/three Form Requests follow the established CRUD shape
exactly. No `MediaDTO` — see Executive Summary.

---

## Storage Design

`config/media.php` centralizes disk (`MEDIA_DISK`, default `public`,
deliberately independent of `FILESYSTEM_DISK`), upload size limit, and
the MIME allow-list (images only this milestone; `svg` deliberately
excluded — see ADR 0010's Security section). Every stored path is
`media/{workspace_id}/{uuid}.{extension}` — never derived from a
user-supplied filename, closing path traversal as a code path rather
than sanitizing an untrusted string. Hash-based dedup reuses an
existing `storage_path` instead of writing identical bytes twice,
verified directly by a dedicated test (two uploads of identical
content produce two `Media` rows but one physical file).

---

## Queue Integration

`DownloadMediaJob` — full Milestone 11 shape (`$tries = 3`, `backoff()
= [10, 30, 60]`, per-post `uniqueId()`, `SerializesModels`). Dispatched
from `WordPressPostMapper::syncFeaturedImage()` (WordPress-post-
specific logic, not the generic `ContentSyncService` orchestrator) with
a guard that no-ops when the post's featured image is already
attached and unchanged — the concrete "do not duplicate downloads"
requirement, verified against a real re-sync scenario where a post's
*other* fields changed but its image didn't. Removing a featured image
is synchronous and IO-free (no job needed — it's a delete, not a
fetch). `GenerateThumbnailJob`/`OptimizeImageJob` (named as examples in
the brief) were not built — nothing in this milestone's scope needs
them; see ADR 0010.

---

## Validation

- `php artisan test`: **120/120 passing** (up from 103) — 17 new
  tests across `tests/Feature/MediaLibraryTest.php` (upload with real
  metadata extraction, disallowed-MIME rejection, oversized-file
  rejection, hash-based dedup verified against real disk state via
  `Storage::fake()`, workspace-scoped listing and filtering, alt-text
  update, delete, cross-workspace isolation, role-gated delete) and
  `tests/Feature/MediaWordPressSyncTest.php` (real featured-image
  download and attachment during sync, no-duplicate-download on
  unchanged re-sync, image replacement on a changed WordPress media
  ID — including the soft-delete/unique-constraint defect this test
  itself caught — image removal when WordPress reports none, real
  per-post job uniqueness against the `database` driver, job
  configuration correctness, and an observable-not-silent failure-
  logging check).
- `./vendor/bin/pint --dirty`: clean (one pre-existing import-order fix
  applied to `Post.php` alongside this milestone's own changes).
- `npm run typecheck` / `npm run lint` / `npm run build`: all pass,
  including the new `/media` route in the production build output.
- Live browser verification (real `php artisan serve` + `queue:work` +
  `npm run start`, not `npm run dev`): logged in, uploaded a real PNG,
  confirmed it appeared in the grid, opened the preview dialog, edited
  and saved alt text, switched to list view, deleted the item, and
  confirmed the library correctly returned to its empty state — all
  against the real backend, not a mock. Verified the posts list and
  post detail pages (which now render a featured-image thumbnail when
  present) against real seeded posts with zero console errors.
  WordPress featured-image *download* itself is verified by the real
  Pest suite above (genuine HTTP fakes, genuine disk writes, genuine
  hash computation) rather than a live browser demo — this
  environment has no real WordPress server to sync against, the same
  constraint every prior milestone's WordPress testing has worked
  within.
- `axe-core`: **zero violations** on the Media Library (empty state),
  the media preview dialog (open, mid-interaction — after the
  contrast fix below), the Dashboard, the posts list, and post detail
  pages.
- Zero console/page errors across the full verification session.

**One real accessibility defect found and fixed during this
verification, not before it.** A `variant="destructive"` Delete button
placed inside `MediaPreviewDialog`'s `DialogFooter` failed WCAG AA
contrast (4.24:1, threshold 4.5:1) — the footer's semi-transparent
`bg-muted/50` background composites differently than the plain page
backgrounds this app's two other destructive buttons already sit on
safely. Fixed by relocating the button out of the footer, not by
overriding the shared `Button` component's color tokens.

---

## Production Readiness

The Media Platform is genuinely reusable: any future producer attaches
via the same polymorphic columns without a schema change, and the
storage layer requires only a config change (`MEDIA_DISK`) to move to
S3/R2/Spaces — the `AWS_*` env vars and `s3` disk have existed since
Milestone 1's Laravel defaults. Security is layered, not assumed:
content-based MIME validation, a closed-off path-traversal surface,
the existing SSRF guard reused for WordPress downloads, workspace-
scoped authorization matching `Post`/`Site`'s pattern exactly, and a
dedicated upload rate limiter. The one real defect this milestone's
own process caught (the soft-delete/unique-constraint collision) was
fixed before it ever shipped, and the equivalent latent risk already
present on `posts`' schema is now named rather than silently
inherited.

---

## Technical Debt Resolved

- **"Content sync fetches only posts, no media"** (named as a
  deliberate Milestone 10 scope limit in `docs/PROJECT.md`'s Known
  Limitations) — resolved for featured images specifically; full post
  body/content remains out of scope, unchanged from Milestone 10.
- **No pre-existing debt was inherited from M8–M11's own Future
  Backlog** that fell within this milestone's scope — reviewed
  `docs/ENGINEERING_JOURNAL.md`'s Future Backlog and confirmed nothing
  there names media/storage/uploads.

---

## Deferred Work

- **Thumbnail/responsive-image generation** (`GenerateThumbnailJob`/
  `OptimizeImageJob`, named as examples in the brief) — not built;
  every rendered image today serves the original resolution. Named as
  Milestone 16 (Performance & Caching)'s natural starting point.
- **Virus scanning** — reviewed per the brief's own instruction,
  explicitly deferred to Milestone 19 (Cloud Deployment & Security
  Hardening); no scanning service exists in any environment this
  project runs in today.
- **Document/report MIME types** (PDF, etc.) — the brief names Reports
  and Attachments as future producers; `config('media.allowed_mimes')`
  is a one-line extension point, not built ahead of a real consumer.
- **Row-level (not just storage-level) deduplication across multiple
  attachments of the same physical file** — considered and rejected
  for this milestone (see ADR 0010's Alternatives Considered); no
  current feature needs one file shared across two attachments with
  independent lifecycles.
- **A `media_mediable` many-to-many pivot** for the same reason above
  — not built ahead of a real need.

---

## Risks

- **No CDN or edge caching** — every image request hits the app's own
  disk/origin directly. Acceptable at this milestone's scale; named as
  Milestone 16's concern, not this one's.
- **`svg` upload is disallowed, closing a real stored-XSS vector, but
  the allow-list itself is enforced only at the application layer** —
  standard for this codebase's existing validation posture (matching
  how every other Form Request enforces its own rules), not a gap
  specific to this milestone.
- **The soft-delete/unique-constraint pattern this milestone
  identified also exists, unexercised, on `posts`' own schema** — not
  a new risk this milestone introduced, but now a named one; worth a
  future session's attention if a post-recreation-after-soft-delete
  scenario ever becomes real.

---

## Future Queue/Media Consumers

- **Milestone 14 (AI-Assisted Content Generation)** — `source =
  'ai_generated'` is already a reserved schema value; an AI image job
  attaches its output through the exact same `MediaService` path
  `DownloadMediaJob` uses today.
- **Milestone 16 (Performance & Caching)** — thumbnailing, responsive
  images, and CDN-backed serving are the direct next layer on top of
  this milestone's storage abstraction.
- **Milestone 19 (Cloud Deployment & Security Hardening)** — object storage migration
  (S3/R2/Spaces) is a `MEDIA_DISK` config change; virus scanning is
  real, deferred infrastructure work for the same milestone.

---

## Recommendation for Milestone 13

Per `docs/ROADMAP.md`, review the roadmap's next milestone in sequence
before starting. This milestone's Media Platform is now available for
any feature needing file storage — a natural fit for Publishing
(Milestone 7's still-unwired `PublishingJob`) if that milestone needs
to attach media to outbound WordPress writes, or for any future
avatar/attachment feature without further Media-layer work. Waiting
for explicit approval before starting, per this milestone's own stop
condition.
