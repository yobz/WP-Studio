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

- [ ] **8. Authentication** — Laravel Sanctum SPA auth between the
      frontend and API (`backend/config/cors.php`'s
      `supports_credentials` and the `sanctum/csrf-cookie` CORS path are
      already prepared for this, unused until now). Wires `auth:sanctum`
      around `routes/api_v1.php`, adds `->authorize()` calls using the
      already-written, already-tested `SitePolicy`/`PostPolicy`
      (`docs/adr/0005-domain-model.md`), and resolves "current
      workspace" for the authenticated user. Must fix the known N+1 risk
      (eager-load `workspace.users`) the moment a policy runs inside a
      list endpoint — see the Engineering Journal's Future Backlog.
      Frontend: real login/logout UI, `ProtectedLayout` (already wired
      as a pass-through since Milestone 4) gets a real check.
- [ ] **9. API Completion & Frontend Migration** — Migrate the
      remaining seven dashboard widgets off `src/services/mock/` onto
      real endpoints, following the pattern Milestones 6–7 established
      (`docs/adr/0004-backend-foundation.md`'s Future Implications).
      Add pagination to the `sites`/`posts` index endpoints (a named
      gap since Milestone 7). Give `analytics`/`settings` real,
      minimal-but-genuine logic where the domain doesn't need a
      dedicated future milestone to design properly.
- [ ] **10. WordPress Integration** — Real WordPress REST API
      connections: the actual OAuth/API-key handshake that creates
      `Site` rows (`SiteController`, `SiteConnected`/`LogSiteConnected`
      are already scaffolded and waiting — `docs/adr/0004-backend-foundation.md`).
      `ServiceUnavailableException` (built, unused since Milestone 6)
      becomes real the moment this calls a real external service.

## Release v0.9 — Async, Extensibility & Quality

Everything that makes the platform behave like a real multi-tenant
product under real usage, not just a single-request demo.

- [ ] **11. Background Jobs & Queues** — A real queue worker
      (`QUEUE_CONNECTION` is already `database` in `backend/.env`)
      processing `PublishingJob` rows past `pending` for the first
      time; `PublishingService::schedule()` already exists as the
      seam this attaches to (`docs/adr/0005-domain-model.md`).
- [ ] **12. Storage & Media** — File/media storage for site and post
      content (local disk today, S3-compatible object storage as the
      production target — a real decision this milestone makes and
      documents, the same way the database choice was deferred and
      named in `docs/adr/0004-backend-foundation.md`).
- [ ] **13. GraphQL Layer** — GraphQL where it adds real value over the
      existing REST API (e.g. dashboard aggregation queries with
      variable shape) — not a wholesale replacement of `/api/v1`.
- [ ] **14. AI-Assisted Content Generation** — The first real AI
      provider integration. Designs and adds the "AI Jobs" schema
      `docs/adr/0005-domain-model.md` explicitly deferred rather than
      guessed at, and connects `AI Assistant Preview`'s already-built
      (currently disabled) `Generate` action to it.
- [ ] **15. Frontend Testing** — Vitest + React Testing Library
      coverage for critical paths, closing the asymmetry flagged in
      every milestone review since M5: the backend has 38 Pest tests,
      the frontend has zero automated tests.

## Release v0.95 — Production Hardening

- [ ] **16. Performance & Caching** — Query/response caching once a
      workspace can realistically have hundreds of sites/posts
      (explicitly premature before now — `docs/adr/0005-domain-model.md`'s
      Performance section); frontend bundle/loading performance pass.
- [ ] **17. Observability** — Wire the Sentry/OpenTelemetry integration
      points that have been documented placeholders since Milestone 6
      (`.env.example`, `ApiExceptionHandler`'s single `render()` choke
      point) into real error tracking and tracing; structured
      application logging beyond today's request-ID correlation.
- [ ] **18. CI/CD & Containerization** — GitHub Actions pipelines for
      lint/typecheck/test/build on every PR, and Docker images for both
      services so "deployable state" (the standing rule at the top of
      this file) becomes a built artifact, not just a claim.
- [ ] **19. Cloud Deployment & Security Hardening** — Real environments
      (Vercel + Railway per `docs/PROJECT.md`'s Stack table, or
      whatever this milestone's own review decides), the deferred
      production database choice (SQLite → MySQL/Postgres, flagged
      since Milestone 6), rate limiting, and a security review of
      everything shipped so far.

## Release v1.0 — Production Release

- [ ] **20. Production Release** — Final hardening, launch checklist,
      and disaster-recovery plan. Every "what changed / what's deferred
      / future considerations" note from Milestones 8–19 gets a final
      pass here — nothing should still be silently deferred by the time
      this milestone closes.
