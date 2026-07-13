# Roadmap

Each milestone must leave the application in a deployable state. Work
proceeds one milestone at a time; the next milestone starts only after
explicit approval.

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
- [ ] **8. Authentication** — Auth flow between frontend and Laravel API
      (Laravel Sanctum — `backend/config/cors.php`'s
      `supports_credentials` and the `sanctum/csrf-cookie` CORS path are
      already prepared for this, unused until now).
- [ ] **9. WordPress Integration** — WordPress REST API connections, site
      management.
- [ ] **10. Testing** — Vitest + RTL coverage for critical paths.
- [ ] **11. CI/CD** — GitHub Actions pipelines for lint/test/build/deploy.
- [ ] **12. Docker** — Containerized local dev/deploy.
- [ ] **13. GraphQL** — GraphQL layer where it adds value.
- [ ] **14. AI Features** — AI-assisted content generation.
- [ ] **15. Production Release** — Final hardening and launch.
