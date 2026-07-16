# Roadmap

Each milestone must leave the application in a deployable state. Work
proceeds one milestone at a time; the next milestone starts only after
explicit approval. Every milestone from Milestone 8 onward follows
`docs/prompts/milestone-lifecycle.md`.

Milestones are grouped into releases — a release is a coherent,
demoable slice of the product, not just a batch of unrelated tickets.
Milestone numbering is continuous across releases.

## Release v0.7 — Completed Foundation

- [x] **1. Project Initialization** — Next.js 15 + React 19 + TypeScript
      scaffold, App Router, `src/` dir, import aliases, feature-first
      folder structure, docs.
- [x] **2. Project Foundation** — Dependencies installed (shadcn/ui,
      Zustand, TanStack Query/Table, React Hook Form, Zod, Recharts),
      theme tokens, Geist/Inter typography, Prettier + Husky +
      lint-staged, VSCode config, `.env.example`, GitHub templates.
- [x] **3. Design System** — Core `ui/` primitives (Button, Input,
      Textarea, Label, Card, Badge, Avatar, Skeleton, Tooltip,
      Typography) via shadcn CLI + hand-built; `common/` composites
      (PageHeader, StatCard, StatusBadge, EmptyState, SearchInput);
      typography scale, iconography conventions, expanded design
      tokens (selection, scrollbar). Remaining primitives (Dialog,
      Table, Tabs, Sheet, Accordion, Popover, Dropdown, Toast) added
      on demand in later milestones, not built speculatively.
- [x] **3.1. Design System Hardening** — Patch milestone resolving the
      Milestone 3 report's findings: WCAG AA contrast fixes (tokens
      only), compile-time `aria-label` enforcement on icon-only
      `Button`, `data-slot` consistency across every component. See
      `docs/MILESTONE_REPORT_M3.md`'s update note.
- [x] **4. Product Shell** — Sidebar (shadcn `sidebar` primitive,
      hand-integrated to protect Milestone 3.1's hardened components),
      header (real breadcrumbs, functional theme toggle, placeholder
      notifications/user menu), configuration-driven navigation
      (`src/lib/navigation.ts`), `(app)` route group with six
      placeholder pages, loading/error/404 states, `ProtectedLayout`
      placeholder ahead of Milestone 8. See `docs/adr/0002-product-shell.md`.
- [x] **5. Dashboard Experience** — Nine dashboard widgets (Welcome,
      KPI Cards, Quick Actions, Recent Activity, WordPress Overview,
      Analytics Preview, Recent Drafts, AI Assistant Preview, System
      Health) on realistic mock data; dedicated mock service layer
      (`src/services/mock/`) shaped like a future REST response;
      TanStack Query for all server state (caching, retry, Loading/
      Error/Empty/Success across every widget); one Zustand store for
      genuinely cross-cutting state (notification count). See
      `docs/adr/0003-dashboard-data-architecture.md`.
- [x] **6. Backend Foundation** — Laravel 12 backend (`backend/`,
      versioned `/api/v1` routes, consistent JSON envelope, centralized
      exception handling, SQLite + migrations/seeders/factories,
      CORS/security-headers/request-ID groundwork, Pest testing).
      Dashboard summary is the one real endpoint; five other domains
      (Sites, Posts, Analytics, AI, Settings) are placeholders. KPI
      Cards is the one frontend widget migrated off the mock service
      layer, proving the pattern — see
      `docs/adr/0004-backend-foundation.md`. Absorbs what the original
      roadmap listed separately as "State Management" (already
      substantially delivered in Milestone 5's query-client/Zustand
      setup — see `docs/adr/0003-dashboard-data-architecture.md`) and
      "Laravel REST API" — both folded into this single milestone
      rather than run as two.
- [x] **7. Domain & Data Platform** — Multi-tenant domain model
      (`Workspace` ↔ `User` membership with roles; `Workspace` → `Site`
      → `Post`/`AnalyticsSnapshot`; `PublishingJob` placeholder). Real
      CRUD (Form Requests, Policies, Resources, Services) for Sites and
      Posts, replacing Milestone 6's placeholders. `AnalyticsSnapshot`
      replaces the denormalized `monthly_visitors` column, enabling a
      real Dashboard trend calculation. 38 Pest tests (Feature/
      Database/Relationship/Validation/Policy). WordPress Overview
      migrated off the mock layer as the second real-API widget. See
      `docs/adr/0005-domain-model.md`.

## Release v0.8 — Platform Completion & Integration

Closes the gap between "architecture proven" (v0.7) and "the product
actually does the thing it's named for": real login, every widget on
real data, and a real connection to a real WordPress site.

- [x] **8. Authentication & Authorization** — Laravel Sanctum cookie/
      session SPA auth (no JWTs, no bearer tokens); `auth:sanctum`
      around every route except `/health` and `/login`.
      `->authorize()` wired into `SiteController`/`PostController`
      using the already-written, already-tested `SitePolicy`/
      `PostPolicy` (`docs/adr/0005-domain-model.md`) — no policy logic
      changed, only wired in. Introduced a **Current Workspace
      Resolver** (`CurrentWorkspaceResolver` → `ResolveCurrentWorkspace`
      middleware → `CurrentWorkspaceContext`) so controllers/services
      depend on an authorized "current workspace," never a
      client-supplied `workspace_id` — architecturally resolves the
      policy N+1 risk flagged in Milestone 7's Future Backlog for list
      endpoints, rather than papering over it with eager loading. Fixed
      two real vulnerabilities the architecture review surfaced:
      `DashboardService` aggregating every workspace regardless of
      tenant, and unauthorized `workspace_id`/`site_id` filters on the
      Sites/Posts index endpoints. Frontend: real login/logout,
      `ProtectedLayout` (wired as a pass-through since Milestone 4) now
      does a real session check with redirect-preserving `/login`,
      `useCurrentUser()` as TanStack Query server state (no Zustand
      auth store — see `docs/adr/0003-dashboard-data-architecture.md`'s
      precedent). Registration, workspace switcher UI, email
      verification, password reset, 2FA, and social auth are named,
      deliberately deferred — see
      `docs/adr/0006-authentication-architecture.md`.
- [x] **9. WordPress Integration Platform** — Real WordPress REST API
      connections via Application Passwords (HTTP Basic Auth) — the
      actual handshake that creates `Site` rows for real, replacing
      Milestone 7's plain-attribute creation (`SiteController`,
      `SiteConnected`/`LogSiteConnected` were already scaffolded and
      waiting — `docs/adr/0004-backend-foundation.md`).
      `ServiceUnavailableException`'s successors (`WordPressConnectionException`/
      `WordPressAuthenticationException`/`WordPressResponseException`)
      become real the moment this calls a real external service. New
      `App\Services\WordPress\` integration layer (Contracts/Client/
      Authentication/DTO/Exceptions/Security) — controllers never talk
      to WordPress directly. SSRF guard and a dedicated rate limiter on
      every connection-testing endpoint. Credentials encrypted in a
      separate `site_credentials` table, never touched by
      `SiteResource`. Frontend: a real Connect Site flow, a sites list,
      and this app's first nested route (`/wordpress/[id]`), which also
      resolved the sidebar `isActive` exact-match gap deferred since
      Milestone 4.1. See
      `docs/adr/0007-wordpress-integration-architecture.md`.
- [x] **10. Content Synchronization Platform** — Redefined from this
      slot's original "API Completion & Frontend Migration" scope
      (preserved below as Milestone 10.1) by explicit brief: the
      platform's first read-back from a connected WordPress site.
      New `App\Services\ContentSync\` — a generic sync engine
      (`ContentSyncService`) parameterized by a `ContentTypeMapper`
      contract, with `WordPressPostMapper` as the only concrete
      implementation, so a future Pages/Media/Categories/Tags sync is
      a new mapper, not a rewrite of the orchestrator. Extended the
      existing `posts` table (nullable `wordpress_post_id`,
      `wordpress_modified_at`, `wordpress_url`, `sync_status`,
      `sync_hash`, `last_synced_at`) rather than a parallel table — a
      synced post and a manually-created one are the same domain
      concept to every consumer. Idempotent via a content-hash
      comparison, not a timestamp alone; a unique
      `(site_id, wordpress_post_id)` index is the real duplicate-import
      guard. Reused the existing, already-workspace-scoped
      `GET /posts?site_id=` endpoint for reads instead of adding a
      duplicate nested route. New `POST /sites/{site}/sync` and
      `GET /sites/{site}/sync-status`, both behind the existing
      `SitePolicy`/rate-limiter/SSRF-guard stack. Frontend: this app's
      second level of route nesting (`/wordpress/[id]/posts[/…]`) and
      the first UI `Post` (built in Milestone 7) has ever had. Fully
      synchronous, named as the exact seam Milestone 11 replaces —
      queues/background jobs/scheduled sync explicitly deferred. See
      `docs/adr/0008-content-synchronization.md`.
- [x] **10.1. API Completion & Frontend Migration** — This slot's
      original Milestone 10 scope, displaced by the redefinition above
      and completed here. Audited all six remaining mocked dashboard
      widgets individually rather than migrating uniformly: Recent
      Activity, Analytics Preview, Recent Drafts, and System Health
      became real; Quick Actions became half-real (two of four actions
      now navigate to real destinations, two stay honestly disabled);
      AI Assistant Preview stays deliberately mocked (Milestone 14's
      job). New real endpoints — `GET /dashboard/activity` (derived
      from existing `Post`/`Site` columns, no new table),
      `GET /analytics` (aggregates the existing `AnalyticsSnapshot`
      table), `GET /system-health` (a shared `DatabaseHealthChecker`
      extracted from `HealthController`, real `Site`-derived
      connection/storage status, an honest placeholder for the
      not-yet-built background queue), `GET /settings` (real
      workspace/user data, deliberately read-only — no product
      decision yet about what's editable). `IndexPostsRequest` gained
      one new accepted `status=unpublished` value reusing the existing
      `Post::scopeUnpublished()` scope for Recent Drafts, instead of a
      parallel endpoint. `src/services/mock/` deleted entirely.
      Pagination on `sites`/`posts` index endpoints was reviewed again
      and deliberately deferred — still needs its own page-size/UI
      decision, tangential to this milestone's actual objective. 95
      backend tests passing (up from 83); zero `axe-core` violations
      on the two pages carrying new real-data content. See
      `docs/MILESTONE_REPORT_M10_1.md`.

## Release v0.9 — Async, Extensibility & Quality

Everything that makes the platform behave like a real multi-tenant
product under real usage, not just a single-request demo.

- [x] **11. Background Job & Queue Platform** — Delivered as a
      reusable asynchronous-processing platform rather than a single
      point feature, per explicit brief. `POST /sites/{site}/sync`
      (Milestone 10) now dispatches `SyncWordPressPostsJob` instead of
      blocking the request — the exact seam Milestone 10's own ADR
      named in advance. A second job, `RefreshSiteMetadataJob`,
      proves the pattern generalizes, consumed by a new daily
      Scheduler task rather than the existing manual "Refresh
      Metadata" button (deliberately left synchronous — fast/bounded
      enough that immediate feedback is still correct UX). Both jobs
      share real retry (3 attempts), exponential backoff, and
      per-resource uniqueness (via Laravel's cache-lock mechanism,
      verified against the real `database` driver in tests, not just
      asserted). System Health's `backgroundQueue` placeholder
      (Milestone 10.1) is now real — a `QueueHealthChecker` reads
      actual `pending`/`failed` counts from the `jobs`/`failed_jobs`
      tables. Frontend polls (`useSite`/`useSyncStatus`, 2s interval,
      only while a resource is actively syncing) instead of blocking
      on a loading spinner, isolated to two hooks so a future
      WebSocket/SSE push mechanism needs no other changes. **Does
      not** wire `PublishingJob`/`PublishingService::schedule()`
      (Milestone 7's placeholder for a future *write-to-WordPress*
      queue) into a real consumer — Publishing itself remains future
      scope; this milestone's job platform is exactly the
      infrastructure that future Publishing milestone will dispatch
      onto, per `docs/adr/0009-background-job-platform.md`'s Future
      Evolution section. See
      `docs/adr/0009-background-job-platform.md`.
- [x] **12. Media Platform & Storage** — A reusable Media domain, not a
      one-off upload feature: `App\Models\Media` is polymorphically
      attachable (`mediable_type`/`mediable_id` + `collection`) so
      every current and future file producer (WordPress featured
      images today; avatars, AI-generated images, attachments, reports
      later) shares one table and one `MediaService`, rather than each
      inventing its own storage code. Extended the Content
      Synchronization Platform (Milestone 10) so a synced post's
      WordPress featured image downloads asynchronously via a new
      `DownloadMediaJob`, built to Milestone 11's exact job shape
      (retries, backoff, per-post uniqueness). Storage goes through
      Laravel's Filesystem abstraction exclusively, behind a dedicated
      `MEDIA_DISK` config value independent of the app's generic
      default — moving to S3/R2/Spaces is a config change, not a code
      change, the local-disk-today/object-storage-later decision this
      slot's original scope asked for. Introduced this project's first
      mandatory **Architecture Drift Review** step, ahead of
      implementation — found the codebase genuinely greenfield for
      this domain, and caught one real, self-inflicted defect during
      implementation (a DB-level unique constraint that broke
      replacing a featured image once `SoftDeletes` was involved,
      fixed by moving that invariant into the service layer — see
      `docs/adr/0010-media-platform.md`'s Alternatives Considered).
      Frontend: a new Media Library (`/media`, grid/list toggle,
      upload, preview/edit/delete), and featured-image thumbnails on
      the existing Posts list/detail pages. 120 backend tests passing
      (up from 103); zero `axe-core` violations, including a real
      contrast defect found and fixed during this milestone's own
      verification. See `docs/adr/0010-media-platform.md` and
      `docs/MILESTONE_REPORT_M12.md`.
- [x] **13. GraphQL Layer** — A single read-only `/api/v1/graphql`
      endpoint (`nuwave/lighthouse`) backing exactly the case this
      entry named: dashboard aggregation with variable shape. Two
      thin query resolvers (`dashboardOverview`, `analyticsPreview`)
      delegate to the exact same `DashboardService`/`AnalyticsService`/
      `SystemHealthService` the REST controllers already call — zero
      duplicated logic — behind the identical `auth:sanctum` →
      `ResolveCurrentWorkspace` middleware stack every REST route
      already uses. Not a wholesale replacement: no mutations, and
      Sites/Posts/Media/WordPress sync/background jobs keep their
      existing REST endpoints entirely unchanged — reviewed and
      explicitly rejected exposing them as GraphQL types too, since
      they already have complete, tested, policy-enforced REST CRUD.
      The Dashboard now fires two GraphQL requests on load instead of
      four separate REST calls; every widget component needed zero
      changes, only its hook's data source. Introduced this project's
      new mandatory Architecture Drift Review step doing real work
      here — it's what kept GraphQL's scope from creeping into a
      parallel Sites/Posts API. Caught two real, non-obvious defects
      during verification: a stale framework cache (the same
      OneDrive-path staleness class documented since Milestone 6)
      silently blocking package registration, and a genuine GraphQL
      semantics gap (enum fields serialize as their schema name, not
      their internal directive value) that broke a live component
      until fixed. 127 backend tests passing (up from 120). See
      `docs/adr/0011-graphql-layer.md` and
      `docs/MILESTONE_REPORT_M13.md`.
- [x] **14. AI-Assisted Content Generation** — The first real AI
      provider integration, and the last named gap from
      `docs/adr/0005-domain-model.md`'s deferred "AI Jobs" schema —
      designed now against two real providers instead of guessed at.
      New `App\Services\AI\` integration layer (`Contracts`/`Client`/
      `DTO`/`Exceptions`) following the same shape
      `App\Services\WordPress\` established: one `AiClientContract`,
      two implementations behind it — `AnthropicMessagesClient`
      (official `anthropic-ai/sdk`, `claude-opus-4-8`) and
      `GeminiClient` (raw HTTP, Google's Generative Language API) —
      selected at runtime by `AI_PROVIDER`, a genuine mid-milestone
      scope change absorbed by the contract with zero changes to any
      caller. New `ai_jobs` table (prompt, status, result, error,
      model, token counts), processed asynchronously through the
      Milestone 11 job platform (`GenerateAiContentJob`, same
      retry/backoff shape as `SyncWordPressPostsJob`) rather than
      blocking a request on unpredictable LLM latency —
      `POST /ai/generate` returns `202 {status: "queued"}` immediately,
      `GET /ai/jobs/{id}` is the poll endpoint the frontend hits every
      2s while pending, the same pattern `useSyncStatus` established.
      Connects `AI Assistant Preview`'s already-built (previously
      disabled) `Generate` action to this pipeline with real Generating/
      Completed/Failed states — no new global state, `useGenerateContent`/
      `useAiJob` are the only new hooks. 142 backend tests passing (up
      from 127). Live verification against the real Gemini API caught a
      genuine external issue (a deprecated default model returning a
      live 404) and confirmed the entire pipeline end-to-end — auth,
      queue processing, retry/backoff, and error handling — before
      being blocked from a full success-path demo by the account's
      free-tier daily quota, documented honestly rather than glossed
      over. See `docs/adr/0012-ai-content-generation.md` and
      `docs/MILESTONE_REPORT_M14.md`.

## Release v0.9 — Engineering Quality

- [ ] **15. Docker Development Environment** — Containerize the
      frontend and backend with Docker Compose, including the queue
      worker and scheduler. A fresh clone should become runnable with
      `docker compose up`, with no undocumented local setup. Continue
      using SQLite as the default development database.
- [ ] **16. Frontend Testing & CI/CD** — Add Vitest + React Testing
      Library coverage for critical frontend flows, closing the
      testing asymmetry between backend and frontend. Introduce GitHub
      Actions to run lint, typecheck, frontend/backend tests, and
      production builds on every pull request.

## Release v0.95 — Production Hardening

- [ ] **17. Performance & Scalability** — Introduce Redis-backed
      caching where justified, optimize expensive queries, improve
      frontend bundle loading, and validate performance under
      realistic datasets. Continue following ADR 0005 by avoiding
      premature optimization.
- [ ] **18. Observability** — Implement structured logging, health
      checks, Sentry/OpenTelemetry integration, request tracing, and
      operational metrics using the integration points established in
      earlier milestones.
- [ ] **19. Cloud Deployment & Security Hardening** — Deploy to Vercel
      and Railway (or alternatives selected during the milestone
      review), migrate from SQLite to MySQL/PostgreSQL if appropriate
      for production, configure object storage (S3/R2) for media,
      review environment configuration, rate limiting, secrets
      management, and perform a security audit of the application.

## Release v1.0 — Production Release

- [ ] **20. Production Release** — Final architecture review,
      production readiness audit, deployment checklist, disaster
      recovery review, documentation cleanup, dependency audit, and
      final polish. Resolve or explicitly document every deferred
      decision from Milestones 1–19 before closing the project.
