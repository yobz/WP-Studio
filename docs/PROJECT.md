# WP Studio

## Overview

WP Studio is a SaaS application for managing one or multiple WordPress
websites from a single dashboard. It focuses on content management,
publishing workflows, analytics, and WordPress integrations, with
AI-assisted content generation planned for a later phase.

Built as a portfolio project demonstrating production-quality full stack
engineering across a Next.js/React frontend and a Laravel/MySQL backend.

## Stack

| Layer            | Choice                              |
| ----------------- | ------------------------------------ |
| Frontend          | Next.js 15, React 19, TypeScript     |
| Styling           | Tailwind CSS 4, shadcn/ui (Base UI primitives), Lucide React |
| Backend           | Laravel 12, PHP 8.2 (`backend/`, own README/ADR)  |
| Database          | SQLite (local dev, Milestone 6); MySQL/PostgreSQL a production candidate, not yet decided |
| Client state      | Zustand, React Context API           |
| Server state       | TanStack Query                       |
| Forms/validation  | React Hook Form, Zod                 |
| Tables/charts     | TanStack Table, Recharts             |
| Testing           | Vitest, React Testing Library (frontend, not yet added); Pest (backend — 38 tests across Feature/Database/Validation/Policy, Milestones 6–7) |
| Deployment         | Vercel (frontend), Railway (backend) |
| CI/CD             | GitHub Actions                       |

Planned later: GraphQL, Docker, cloud deployment hardening, AI integration.

## Architecture

Feature-first organization under `src/`. Each feature in `src/features/`
owns its own `components/`, `hooks/`, `services/`, `types/`, and `utils/`.
Shared, cross-feature code lives in the top-level `components/`, `hooks/`,
`lib/`, `services/`, `store/`, `types/`, and `utils/` directories.

## Theming

Design tokens live in `src/app/globals.css` as CSS custom properties,
bridged into Tailwind's theme via `@theme inline` (Tailwind v4's
CSS-first config — there is no `tailwind.config.ts`). Tokens cover
color (including `success`/`warning` alongside shadcn's standard
`destructive`), radius, shadows, and transition duration. Dark mode is
class-based (`.dark` on `<html>`), not just `prefers-color-scheme`, so
a manual theme toggle can be added later. Base color palette is
neutral grayscale; `shadcn/ui` is configured with Base UI (not Radix)
as its primitive library, per the CLI's current default.

Typography: Geist (primary) with Inter as an explicit fallback, both
self-hosted via `next/font/google` for zero layout shift.

Milestone 3 additions: `::selection` styling, a minimal styled
scrollbar (thin, subtle thumb, matches theme), and `destructive-foreground`
was filled in (the generated preset omitted it). Container widths and
hover/disabled states are handled as **conventions**, not new tokens —
Tailwind's default `max-w-*` scale and each component's own
`hover:`/`disabled:` utilities are already sufficient; adding parallel
custom tokens for these would just duplicate what Tailwind provides.

**Milestone 3.1 — contrast correction.** The light-mode `success`,
`warning`, `destructive`, and `muted-foreground` token values shipped in
Milestones 2–3 failed WCAG AA (as low as 2.1:1 against the 4.5:1
requirement) when used as text — confirmed both by an automated
`axe-core` audit and by an independent empirical contrast script
(Canvas2D-resolved sRGB, not guessed). All four were darkened in light
mode only (dark mode already passed); `warning-foreground` was also
flipped from near-black to near-white so it still reads on the new,
darker `warning` fill as a solid background, and to match how
`success-foreground`/`destructive-foreground` already work. Every
combination — text on the page background, text on its own `/10`
(light) or `/20` (dark) tinted badge background, and solid-fill with
foreground text — was individually verified to clear 4.5:1. Re-running
the same axe-core audit after the fix returned zero violations in both
light and dark mode. Fixed at the **token** level only, per the
milestone's explicit instruction — no component received a one-off
color override.

**Focus ring precedence.** The global `:focus-visible` rule in
`globals.css` (`outline-2 outline-offset-2 outline-ring`) is a fallback
for plain elements with no custom focus treatment. Every shadcn-generated
primitive (Button, Input, Textarea, ...) sets `outline-none` and defines
its own `focus-visible:ring-3 ring-ring/50` box-shadow ring instead —
Tailwind's utilities layer always wins over `@layer base`, regardless of
source order, so the component-level ring correctly takes precedence.
Both are visible and accessible; this is intentional layering, not a bug
(verified visually — see `DEVLOG.md`).

## Design System

Reusable UI lives in `src/components/`, split by role:

- **`ui/`** — low-level primitives generated via the shadcn CLI (Base
  UI–backed, accessible, dark-mode aware out of the box) plus a
  hand-built `typography.tsx`. Current set: Button, Input, Textarea,
  Label, Card, Badge, Avatar, Skeleton, Tooltip, Typography. This is a
  deliberately trimmed core set (not the full example catalog from the
  milestone brief) — more primitives (Dialog, Table, Tabs, Sheet, ...)
  get added on demand when a specific feature milestone needs them,
  rather than speculatively upfront.
- **`common/`** — reusable, business-agnostic composites built from
  `ui/` primitives: `PageHeader`, `StatCard`, `StatusBadge`,
  `EmptyState`, `SearchInput`. No feature-specific logic lives here —
  e.g. `StatusBadge` takes a generic `status` union
  (`success | warning | error | neutral`), not domain terms like
  "connected/disconnected"; a feature maps its own states onto that
  generic vocabulary.
- **`layout/`** (Milestone 4) — `AppSidebar`, `AppHeader`,
  `DashboardLayout`, `ProtectedLayout`. Structural, single-consumer
  composition components (unlike `ui/`/`common/`, they don't carry
  `data-slot` — there's no external styling API to stabilize when the
  only consumer is the app itself).

**Typography scale** (`ui/typography.tsx`): `display`, `h1`–`h4`,
`body`, `body-sm`, `caption`, `label`, `code`, each with a sensible
default HTML tag, overridable via an `as` prop. `body`/`body-sm` use
`text-sm`/`text-xs` (not the more common `text-base`/`text-sm`) to
match the compact density already established by the generated
primitives (`h-8` buttons, `text-sm` inputs) — one consistent density
across the whole system rather than two competing scales.

**Iconography** — Lucide React only, no mixed icon libraries. Default
size is inherited automatically (`size-4`) via each primitive's own
`[&_svg:not([class*='size-'])]:size-4` selector; only override size
explicitly when a context calls for it (e.g. `size-5` in `EmptyState`).
Conventions by purpose:

- **Navigation** — one icon per destination, representing the section
  (e.g. `LayoutDashboard`, `Globe`, `FileText`, `BarChart3`,
  `Settings`).
- **Status** — paired with `StatusBadge`/`StatCard` trends
  (`TrendingUp`/`TrendingDown`/`Minus`, `CheckCircle2`, `AlertTriangle`,
  `XCircle`).
- **Action** — inside buttons for operations (`Plus`, `Pencil`,
  `Trash2`, `RefreshCw`, `Search`, `X`).
- **Content** — identifies entity type at a glance in lists/cards
  (`FileText` for posts, `Image` for media, `Globe` for sites, `Users`
  for authors).

**Component quality.** Every `ui/` primitive supports dark mode,
keyboard navigation, and visible focus out of the box (Base UI
foundation). `Button` additionally supports a `loading` prop (disables
the button, shows a spinning `Loader2`, sets `aria-busy`) — added during
self-review since "Loading" is an explicitly required state and Button
is the component most likely to need it (form submits, async actions).

**Icon-only button naming (Milestone 3.1).** `Button`'s props are a
discriminated union: when `size` is one of the icon sizes (`icon`,
`icon-xs`, `icon-sm`, `icon-lg`), `aria-label` becomes a **required**
prop — a compile-time `tsc` error, not a lint warning or a runtime
check, if omitted. This is the chosen convention (over an alternative
`iconOnly` boolean prop) because the icon sizes already unambiguously
signal intent; a separate flag would be redundant API surface. A
`Tooltip` is not a substitute for this — it's a hint for sighted
pointer/keyboard users, not an accessible name for screen readers.

```tsx
// Compile error: Property '"aria-label"' is missing
<Button size="icon"><Bell /></Button>

// Correct
<Button size="icon" aria-label="Notifications"><Bell /></Button>
```

**Data-slot convention.** Every component now exposes a `data-slot`
DOM attribute uniquely identifying it (`badge`, `stat-card`,
`status-badge`, etc.) — `Badge` and the two `common/` composites that
were missing it were fixed in Milestone 3.1. `data-state`/`data-disabled`
are not something this project sets manually — Base UI's primitives
inject them consistently on their own, and the `@custom-variant
data-open`/`data-disabled`/etc. rules in `globals.css` already hook
into that shared convention uniformly across every primitive.

## Product Shell (Milestone 4)

**Routing.** `src/app/(app)/` is a Next.js route group holding the
shell `layout.tsx` plus six pages (`/`, `/dashboard`, `/content`,
`/wordpress`, `/analytics`, `/settings`) — the group is transparent to
the URL and scopes the sidebar/header shell to exactly these routes,
leaving room for a future `(auth)` group (Milestone 8) that won't get
the dashboard chrome. `src/app/not-found.tsx` deliberately sits
**outside** the group — a global 404 shouldn't assume the shell is
relevant. See `docs/adr/0002-product-shell.md` for the full reasoning.

**Navigation model.** Entirely configuration-driven: `src/lib/
navigation.ts` exports one array (`{ title, href, icon }`, grouped),
which both `AppSidebar` (rendering) and the header's breadcrumb
resolver (`getNavTitle()`) read from. Adding a future module is one
config entry plus one route folder — no edits to `AppSidebar` or
`AppHeader` themselves.

**Sidebar.** Built on shadcn's own `sidebar` primitive (collapsible,
responsive — becomes a `Sheet`-based drawer under the `md` breakpoint,
keyboard shortcut `Cmd/Ctrl+B`) rather than hand-rolled, per
`docs/adr/0002-product-shell.md`. Integrated by hand (not via `npx
shadcn add sidebar`) to avoid the CLI overwriting the Milestone 3.1
hardened `Button`/`Input`/`Skeleton`/`Tooltip` — see
`docs/ENGINEERING_JOURNAL.md`. Two accessible-name gaps in the
vendor source itself (`SidebarTrigger`, `SheetContent`'s close button)
were caught by this project's stricter `Button` type and fixed during
integration.

**Header.** Breadcrumbs are real (derived from the current pathname via
the same nav config, not hardcoded per page). Search input is
presentational only (no backend yet) — full on desktop, an
icon-triggered inline-expanding search on mobile (Milestone 4.1;
`docs/adr/0002-product-shell.md` has the alternatives considered).
Notifications is a `Popover` reusing the existing `EmptyState`
component ("No notifications"). Theme toggle is fully functional
(`next-themes`, `defaultTheme="dark"`, flash-free). User menu is a
`DropdownMenu` with placeholder items (`Profile`/`Sign out` disabled —
not fake-clickable; `Settings` links to the real route).

**Auth boundary.** `ProtectedLayout` is wired into the route group now
as a pass-through, ahead of Milestone 8 actually needing it — every
shell route already sits behind this boundary, so Authentication only
has to implement the check in one place.

**UX states.** `(app)/loading.tsx` (skeleton, scoped so only the
content region re-renders during navigation — the shell chrome stays
mounted), `(app)/error.tsx` (client error boundary, reuses
`EmptyState` + a retry action), `not-found.tsx` (global 404, also
`EmptyState`-based). No separate "Empty Layout" component — every
placeholder page's content is `PageHeader` + `EmptyState`, which
already covers the need without a redundant wrapper.

**Landmarks (Milestone 4.1).** Every route now has exactly one `<main>`
— `SidebarInset` provides it for the six shell pages; `not-found.tsx`
provides its own, since it deliberately sits outside the shell.
`DashboardLayout` previously nested a second `<main>` inside
`SidebarInset`'s; fixed by using a plain `<div>` there instead.

**Heading hierarchy (Milestone 4.1).** `EmptyState`'s title heading
level is now a `titleAs` prop (`"h1" | "h2" | "h3"`, default `"h2"`)
instead of a hardcoded `<h3>` — the hardcoded version skipped a level
after every `PageHeader`'s `<h1>`, and left `not-found.tsx`/
`(app)/error.tsx` (where `EmptyState` is the only heading on the page)
with no `<h1>` at all. Both now pass `titleAs="h1"` explicitly.

## Dashboard Experience (Milestone 5)

**Data flow.** Every widget reads from `src/services/mock/
dashboard.service.ts` (Promise-returning functions with a simulated
network delay, standing in for a future Laravel REST API) through a
thin `useQuery` wrapper hook in `src/features/dashboard/hooks/`. Query
keys are namespaced under `["dashboard", ...]`. Shared caching/retry
defaults (`staleTime: 60s`, `retry: 2`, exponential backoff) live once
in `QueryProvider` (`src/components/common/query-provider.tsx`, wired
into the root layout) rather than per hook. Domain types
(`src/features/dashboard/types/dashboard.types.ts`) are deliberately
plain data with no icon/component references, so they can be satisfied
by a real API response later without changing the type file. Full
reasoning in `docs/adr/0003-dashboard-data-architecture.md`.

**Widgets, in page order.** Welcome Section (greeting/description/
date/workspace-selector placeholder, no auth), KPI Cards (5 metrics,
composes the existing `StatCard`), Quick Actions (4 static, genuinely
`disabled` placeholder cards — no destination to guard, unlike real
links elsewhere), Recent Activity (mock timeline), WordPress Overview
(one mock site), Analytics Preview (Recharts area chart, local 7D/30D/
90D range toggle — not global state, since nothing else on the
dashboard needs to react to it), Recent Drafts (the one widget with a
deterministic mock failure — fails the first two loads per session,
succeeds after, to reliably demonstrate the Error state and manual
retry), AI Assistant Preview (prompt textarea + suggested prompts,
`Generate` genuinely disabled — not connected, future integration
point documented inline), System Health (service status badges +
`Progress` for storage).

**Async states.** Every data-backed widget handles Loading (`Skeleton`
or the new `LoadingState` common component — added this milestone
since no existing primitive covered a centered spinner-plus-message
placeholder), Error (`EmptyState` + a "Try again" `refetch()` button),
Empty (where meaningful — e.g. zero KPIs, zero drafts), and Success,
composing existing primitives throughout rather than introducing
per-widget one-offs.

**State management.** One new Zustand store this milestone
(`src/store/notification-store.ts`, notification count), reused by the
header's existing notification `Popover`. Deliberately the only global
store added — the brief's own example of "Dashboard Filters" as
Zustand-worthy state was reconsidered and rejected during
implementation (see the ADR): nothing outside Analytics Preview reacts
to its time range, so that stays local `useState`.

**Server/Client boundary.** The dashboard page itself
(`src/app/(app)/dashboard/page.tsx`) is a Server Component composing
Client Component widgets. Every widget that fetches via `useQuery` is
necessarily a Client Component (`useQuery` is a client hook) except
`QuickActions`, which is fully static. Accepted as the correct trade-off
for this milestone, not an oversight — see the ADR's "Server vs. Client
Components" section.

## Backend Foundation (Milestone 6)

**Location and status.** `backend/` is a self-contained Laravel 12
application (own `composer.json`, `.env`, `README.md`) — see
`backend/README.md` for local setup and `docs/adr/0004-backend-foundation.md`
for the full architecture, every trade-off, and the future migration
path. No authentication yet (Milestone 8); every route is currently
open. Architecture only — no production business logic.

**API.** Versioned from the start: `routes/api.php` composes versions,
`routes/api_v1.php` holds the actual routes, so `/api/v2` is additive,
not a rewrite. Every response uses one JSON envelope
(`App\Http\Support\ApiResponse`): `{"success": true, "data", "meta"?}`
on success, `{"success": false, "error": {"code", "message", "details"?}, "request_id"?}`
on failure — the latter rendered centrally by `App\Exceptions\ApiExceptionHandler`
for every failure mode (validation, not-found, unhandled exception),
so a frontend consumer branches on one shape regardless of what failed.

**Endpoints (Milestone 6 state — see Milestone 7 below for what
changed).** `GET /api/v1/dashboard/summary` is real — backed by
`DashboardService`, aggregating the `sites`/`posts` tables. `sites`,
`posts`, `analytics`, `ai`, `settings` are placeholders (200 with
empty/minimal data), one per domain the brief named, proving the
route/versioning/envelope pattern before any of those five have real
logic. `GET /api/v1/health` checks the actual database connection,
separate from Laravel's own built-in `/up`.

**Database (Milestone 6 state).** SQLite locally; two foundational
tables (`sites`, `posts`) matching the WordPress Sites and Posts
domains, with a `SiteSeeder` shaped to resemble the frontend's own
mock fixtures. No repository layer (see the ADR's reasoning — nothing
to abstract yet); one DTO (`DashboardSummaryData`) for the one
endpoint with real aggregation logic.

**Observability and security groundwork, not yet wired to anything
external.** `AssignRequestId` middleware tags every request/response/log
line with a correlation ID; `SecureHeaders` middleware sets baseline
response headers; `config/cors.php` restricts cross-origin requests to
the frontend's own origin (not the framework's wildcard default).
Sentry/OpenTelemetry are documented integration points
(`.env.example` placeholders), not implemented.

**Frontend integration — the mock-to-real pattern.** `src/lib/api-client.ts`
is the one place that calls the real API and unwraps its envelope.
KPI Cards was the one widget migrated this milestone
(`src/services/api/dashboard.service.ts` +
`src/features/dashboard/utils/map-summary-to-kpis.ts` map the API's
raw numeric response into the exact `Kpi[]` shape the widget already
consumed) — `kpi-cards.tsx` itself needed **zero** changes, only its
hook's data source.

## Domain & Data Platform (Milestone 7)

**Tenancy.** `Workspace` is now the tenant boundary every domain
concept hangs off — every `Site` belongs to exactly one `Workspace`;
a `User` can belong to more than one, via a `workspace_user` pivot
carrying a `role` (owner/admin/member). Full reasoning, entity
relationships, and every schema trade-off in
`docs/adr/0005-domain-model.md`.

**Real CRUD.** `sites` and `posts` are no longer placeholders —
`Route::apiResource` gives both full `index`/`show`/`store`/`update`/
`destroy`, validated by Form Requests (`StoreSiteRequest`,
`UpdateSiteRequest`, `StorePostRequest`, `UpdatePostRequest`,
`IndexSitesRequest`, `IndexPostsRequest`), authorized by real (if not
yet route-wired) `SitePolicy`/`PostPolicy` logic, and rendered through
`SiteResource`/`PostResource`. `analytics`, `ai`, `settings` remain
placeholders. `Site` and `Post` are soft-deletable (`SoftDeletes`) —
recoverable, not destructive.

**Real analytics history.** `AnalyticsSnapshot` (one row per site per
day) replaces Milestone 6's denormalized `sites.monthly_visitors`
column. `DashboardService` now computes a genuine period-over-period
visitor trend (trailing 14 days vs. the 14 before that) instead of a
single point-in-time number — closing a gap flagged in the Milestone 5
and 6 reviews. The frontend's Monthly Visitors KPI now shows a real
trend arrow, verified live against the running backend.

**Placeholder for future queues.** `PublishingJob` (one row per
publish attempt, status `pending`/`processing`/`completed`/`failed`)
and `PublishingService::schedule()` establish the shape a future
queued "actually publish to WordPress" job will update — nothing
processes these yet.

**Testing.** 38 Pest tests across 6 files: Feature (full HTTP CRUD
flows for Sites and Posts), Database/Relationship (`Workspace`↔`Site`
↔`Post`↔`AnalyticsSnapshot`↔`PublishingJob`, cascading deletes, the
`workspace_user` pivot, model scopes), Validation (every Form
Request's rules, asserted against this API's actual error envelope
shape, not Laravel's default), and Policy (`SitePolicy` tested
directly against real workspace roles, ahead of Milestone 8 wiring it
into routes).

**Second widget migrated.** WordPress Overview now reads
`GET /api/v1/sites?status=connected` via `src/services/api/sites.service.ts`
+ a mapper, same zero-widget-changes pattern as KPI Cards — including
a real Empty state for "no connected site" (a case the mock layer's
fixture data never needed, since it always had exactly one site).

## Known Limitations

- `Card`, `Badge`, and other primitives expose more variants (e.g.
  `ghost`, `link` on `Badge`) than `common/` components currently use —
  intentional; they're general-purpose primitives, and unused variants
  cost nothing until a real use case needs them.
- No automated component/route tests yet (Testing is Milestone 10).
  Every design-system and shell change is verified manually: a real
  browser (Playwright) across breakpoints/interactions, plus an
  `axe-core` audit — widened as of Milestone 4.1 to include
  `best-practice`-tagged rules, not just strict WCAG-tagged ones, after
  the narrower scope missed a real nested-landmark defect in Milestone
  4 (see `docs/ENGINEERING_JOURNAL.md`) — run against a temporary
  preview (or the real shell routes) and confirmed clean before the
  milestone is called done. Real, repeated, and has caught genuine
  defects every time it's been run — but it's manual, not a CI gate,
  until Milestone 10/11.
- No "skip to content" link ahead of the sidebar navigation. Not
  flagged by `axe-core`'s WCAG 2.4.1 (Bypass Blocks) check — satisfied
  here by proper `<nav>`/`<main>` landmark structure, which assistive
  tech can jump between directly — but a visible skip link is still a
  reasonable future enhancement once real page content (not
  placeholders) makes "long tab order to reach content" a bigger cost.
- `isActive = pathname === item.href` (exact match) in `AppSidebar`
  won't highlight a parent nav item once nested/detail routes exist
  (e.g. a future `/content/[id]`). Deliberately left unchanged in
  Milestone 4.1 — no such route exists yet to design the right matching
  rule against, and guessing risks getting it wrong. Documented inline
  and in `docs/adr/0002-product-shell.md` for whoever adds the first
  nested route.
- Six of nine dashboard widgets are still mocked (KPI Cards and
  WordPress Overview are real as of Milestones 6–7) — no auth exists
  yet, either on the frontend or any backend route. Recent Drafts'
  error demo is module-scoped (resets on full page reload, not
  client-side navigation) — a known, accepted quirk of demoing a
  deterministic failure against mock data; see
  `docs/ENGINEERING_JOURNAL.md`.
- AI Assistant Preview has no backend — `Generate` is intentionally
  disabled. Future integration point documented inline in
  `src/features/dashboard/components/ai-assistant-preview.tsx`; no
  "AI Jobs" table exists yet either (deliberately deferred — see
  `docs/adr/0005-domain-model.md`).
- Every backend API route is currently unauthenticated (Milestone 8
  adds Sanctum) — acceptable for local development against seeded demo
  data, not for any real deployment. `SitePolicy`/`PostPolicy` contain
  real, tested authorization logic ready to be wired in, but no route
  calls `authorize()` yet. See `docs/adr/0004-backend-foundation.md`
  and `docs/adr/0005-domain-model.md`.
- No repository layer, no real analytics *events* schema (only daily
  snapshots), no pagination on Sites/Posts index endpoints, no
  dedicated workspace-deletion flow, SQLite (not a server database) in
  local development — all deliberate Milestone 6–7 scope decisions,
  not gaps; see both ADRs' Trade-offs sections for the reasoning
  behind each, and `docs/ENGINEERING_JOURNAL.md`'s Future Backlog for
  what each unblocks.

## Status

Milestone 7 (Domain & Data Platform) complete. See `ROADMAP.md` for
the full milestone list, `DEVLOG.md` for a running log of completed
work, and `docs/adr/` / `docs/ENGINEERING_JOURNAL.md` for architectural
decisions and the reasoning behind them.
