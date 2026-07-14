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
| Auth              | Laravel Sanctum (cookie/session SPA auth, Milestone 8) |
| Database          | SQLite (local dev, Milestone 6); MySQL/PostgreSQL a production candidate, not yet decided |
| Client state      | Zustand, React Context API           |
| Server state       | TanStack Query (includes auth session state, Milestone 8) |
| Forms/validation  | React Hook Form, Zod                 |
| Tables/charts     | TanStack Table, Recharts             |
| External integrations | WordPress REST API (Application Passwords, `Illuminate\Http\Client`, Milestone 9) |
| Testing           | Vitest, React Testing Library (frontend, not yet added); Pest (backend — 73 tests across Feature/Database/Validation/Policy/Authentication/Authorization/WordPress Integration, Milestones 6–9) |
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

## Authentication & Authorization (Milestone 8)

**Real login, at last.** Laravel Sanctum in cookie/session (SPA) mode —
no JWTs, no bearer tokens anywhere in the frontend. `POST /api/v1/login`,
`POST /api/v1/logout`, `GET /api/v1/user` are real; every other `/api/v1`
route now requires an authenticated session. Full reasoning, every
alternative considered, and the future IAM roadmap in
`docs/adr/0006-authentication-architecture.md`.

**Current Workspace Resolver.** The frontend never sends a
`workspace_id` it has to remember to attach — `CurrentWorkspaceResolver`
resolves "the workspace this request operates on" once per request
(an explicit `X-Workspace-Id` header/`workspace_id` query param,
membership-checked, or the user's earliest-joined workspace by
default), hands it to controllers via `CurrentWorkspaceContext`
(a `scoped()` container binding), through the `ResolveCurrentWorkspace`
middleware. `SiteController`/`PostController`/`DashboardController` all
depend on this instead of trusting a client-supplied ID. Designed so a
future workspace switcher, subdomain-based tenancy, or a different
resolution convention changes one class, not every controller.

**Two real vulnerabilities closed, not just "auth added on top."**
`DashboardService::summary()` previously aggregated every `Site` in the
database regardless of tenant — invisible with one seeded workspace,
a real cross-tenant leak the moment a second exists. `IndexSitesRequest`/
`IndexPostsRequest` accepted any `workspace_id`/`site_id` with no
membership check. Both fixed as part of this milestone — see the ADR's
Context section for how the architecture review surfaced them.

**The policy N+1 risk flagged in Milestone 7's Future Backlog is
resolved architecturally**, not papered over with eager loading:
`index()` actions never authorize per-row — membership in the resolved
workspace is already guaranteed before the controller runs, so listing
is one `WHERE workspace_id = ?` query, not N per-row Gate checks.

**Frontend.** `src/lib/api-client.ts` now sends `credentials: "include"`
on every request and handles the CSRF cookie handshake centrally (one
choke point, like the envelope-unwrapping it already centralized).
`useCurrentUser()` (`src/features/authentication/hooks/use-auth.ts`) —
TanStack Query, not a Zustand store, per the same client/server-state
split `docs/adr/0003-dashboard-data-architecture.md` already
established — is the single source of truth for "who is logged in."
`ProtectedLayout` is real now: a loading state while the session check
is in flight, a redirect to `/login?redirect=<path>` on no session
(destination preserved), the actual app otherwise. New `(auth)` route
group (`/login`) mirrors how `(app)` is its own group. `AppHeader`'s
user menu (previously disabled placeholders) now shows the real signed-
in user and has a working "Sign out."

**Deliberately deferred, not forgotten** — registration, workspace
switcher UI, email verification, password reset, 2FA, social auth. Each
is named with its specific reasoning in the ADR's Trade-offs and Future
IAM Roadmap sections, not silently dropped.

## WordPress Integration Platform (Milestone 9)

**Real connections, at last.** `POST /api/v1/sites` is now a genuine
WordPress handshake — name, URL, WordPress username, and an
Application Password go in; `App\Services\WordPress\SiteConnectionService`
calls the real site's REST API, and only creates a `Site` row if that
handshake actually succeeds. Full reasoning, every alternative
considered, and the security model in
`docs/adr/0007-wordpress-integration-architecture.md`.

**A dedicated integration layer, not logic scattered across
controllers.** `App\Services\WordPress\` (`Contracts`, `Client`,
`Authentication`, `DTO`, `Exceptions`, `Security`) is the only code in
this application that ever makes an HTTP request to a WordPress site.
`SiteController` never talks to WordPress directly — every action
delegates to `SiteConnectionService`, which depends on
`WordPressClientContract` (bound to `HttpWordPressClient`), never a
concrete HTTP call.

**Two vulnerabilities this feature could easily have introduced,
closed before they existed.** Connecting to a URL a workspace member
supplies is a request-forgery primitive without a check —
`App\Services\WordPress\Security\UrlSafetyValidator` rejects
non-http(s) schemes, local hostnames, and private/reserved IP
addresses *before* any request is sent. A dedicated rate limiter
(`wordpress-connection`, 10/minute) stops these endpoints from being
used to issue repeated outbound requests to an arbitrary target on
demand.

**Credentials, encrypted, in their own table.** `site_credentials` is
a separate table from `sites` — `SiteResource` never touches it, the
Application Password is stored via Eloquent's `encrypted` cast, and
`SiteCredential` marks it `$hidden`. Four independent layers stand
between this data and an API response; see the ADR's Security section.

**Graceful degradation, not an all-or-nothing handshake.** Two REST
calls are load-bearing (proves the URL is really WordPress; proves the
credential works); three more (theme, plugin count, user count) are
individually capability-gated by WordPress itself and best-effort — an
Application Password that isn't a full administrator's still connects
successfully, just with those fields `null` rather than the whole
attempt failing.

**An honest accounting of what's detectable.** `wordpress_version` and
`php_version` are real columns, always `null` today — stock WordPress
doesn't expose either through its public REST API without a companion
plugin. Every other metadata field (theme, plugin count, user count,
timezone, language) comes from a real WordPress REST API response this
integration actually calls. See the ADR's "Version Detection" section.

**Frontend.** `/wordpress` is real now — a Connect Site dialog (React
Hook Form + Zod, the established stack), a sites grid with live status
badges, and `/wordpress/[id]` — the first dynamic/nested route in this
app, which also resolved a standing deferred decision: `AppSidebar`'s
`isActive` now matches a route prefix, not just an exact path (see
`docs/ENGINEERING_JOURNAL.md`'s Future Backlog). Verify/refresh/
disconnect/remove actions all live on the detail page, backed by
TanStack Query mutations that invalidate the sites list on completion.

## Content Synchronization Platform (Milestone 10)

**Real content, at last.** `POST /api/v1/sites/{site}/sync` pulls a
connected site's real WordPress posts via `/wp-json/wp/v2/posts` and
persists them locally — the first time this application reads content
back from an external WordPress site rather than only connection
metadata. Full reasoning, every alternative considered, and the
security/performance model in
`docs/adr/0008-content-synchronization.md`.

**`Post` finally has a frontend.** `Post`/`PostController` have
existed since Milestone 7 (full CRUD, Policy, Resource) but never had
a UI. This milestone's real question wasn't "how do we model synced
content" in isolation — it was whether a WordPress-synced post belongs
in the same table as a manually-created one. Decided yes: `posts`
gained nullable sync-tracking columns (`wordpress_post_id`,
`wordpress_modified_at`, `wordpress_url`, `sync_status`, `sync_hash`,
`last_synced_at`) rather than a parallel table, so every existing and
future consumer of `Post` treats both origins as the same domain
concept.

**A generic sync engine, one concrete content type.** New
`App\Services\ContentSync\` — `ContentSyncService` is a generic
orchestrator (fetch → map → hash → upsert → report) parameterized by a
small `ContentTypeMapper` contract; `WordPressPostMapper` is the only
implementation this milestone builds. A future Pages/Media/Categories/
Tags sync is a new mapper plus whatever local table shape that content
type needs — zero changes to the orchestrator. Deliberately not a
generic polymorphic content table now — the same "don't guess a schema
before a second real content type exists" discipline
`docs/adr/0005-domain-model.md` already applied to deferring the "AI
Jobs" table.

**Idempotent by content hash, not just a timestamp.** Every sync run
computes a hash of each item's mapped, change-relevant fields and
compares it against the stored `sync_hash` before writing — unchanged
content is skipped entirely (no write), not just assumed unchanged
from a WordPress-reported timestamp alone. A unique
`(site_id, wordpress_post_id)` index is the actual duplicate-import
guard. Verified directly: re-syncing identical content twice produces
zero new rows on the second run; changing one field produces exactly
one update, not a duplicate.

**Reuses the existing `Post` read surface, doesn't duplicate it.**
`GET /api/v1/posts?site_id={id}` (built in Milestone 7, already
workspace-scoped) is what the frontend's Posts list actually calls —
no new nested `sites/{site}/posts` route was added, since one would
have duplicated `PostController::index`'s existing, already-correct
query for a cosmetic URL difference. The only genuinely new routes are
`POST /sites/{site}/sync` and `GET /sites/{site}/sync-status`, neither
of which had an existing home.

**Frontend.** `/wordpress/[id]/posts` and
`/wordpress/[id]/posts/[postId]` — this app's second level of route
nesting, following the pattern `/wordpress/[id]` established in
Milestone 9. Four new components (`PostsTable`, `PostDetail`,
`SyncButton`, `SyncSummary`) compose only existing primitives — no new
UI primitive was needed. `SyncButton` and `SyncSummary` coordinate
entirely through TanStack Query cache invalidation, the same mechanism
`useDisconnectSite`/`useVerifyConnection` already use.

**Synchronous today, named seam for Milestone 11.**
`ContentSyncService::sync()` is unchanged by a future move to a queued
job — a worker calling it instead of a controller calling it inline is
the entire migration. `SiteStatus::Syncing`, present in the enum since
Milestone 6/7 but never used until a queued job can meaningfully
report "in progress," is the natural status that job sets.

## API Completion & Frontend Migration (Milestone 10.1)

**The last mock data left the frontend, deliberately, one widget at a
time.** Redefined from this ROADMAP slot's earlier Milestone 10
scope, displaced when Milestone 10 itself was redefined to Content
Synchronization. Of the six dashboard widgets still on
`src/services/mock/` (now deleted entirely), four became real backend
endpoints; the remaining two were reviewed and kept honestly
placeholder rather than migrated for its own sake. Full reasoning in
`docs/MILESTONE_REPORT_M10_1.md`.

**Recent Activity, derived, not logged.** `GET /api/v1/dashboard/activity`
composes three real queries — recently published posts, recently
created drafts, recently connected sites — directly from existing
`Post`/`Site` columns, merged and sorted in `DashboardService`. No new
"activity log" table exists; the timestamps already on those models
were already authoritative.

**Analytics Preview and System Health both had real data waiting.**
Analytics Preview now aggregates the same `AnalyticsSnapshot` table
the Dashboard summary's trend calculation already uses (Milestone 7),
zero-filled per day across the requested range. System Health derives
`apiStatus` from a shared `DatabaseHealthChecker` (extracted from
`HealthController` to remove duplication), `wordpressConnection` from
real `Site.status` values, and `storageUsedPercent` from real
`Site.storage_used_mb`/`storage_limit_mb` sums — only `backgroundQueue`
stays an honest, hardcoded placeholder, since no real queue exists
until Milestone 11.

**Recent Drafts reuses `Post::scopeUnpublished()`, not a new
endpoint.** `IndexPostsRequest` accepts `status=unpublished` as a
sentinel value alongside the real `PostStatus` enum values,
`PostController::index()` branches to the existing scope — one
endpoint, one Policy path, not a duplicate. `PostResource` gained a
`site_name` field (eager-loaded everywhere it's returned) so widgets
never need a second request to show which site a post belongs to.

**Settings is real, not editable — a deliberate, named split.**
`GET /api/v1/settings` returns genuine workspace and user data instead
of "not yet implemented," but there's no form or `PATCH` endpoint —
building one would mean guessing what a user should be able to change
with no product decision behind it, the same category of deferred
scope as Registration (Milestone 8).

**Quick Actions, honestly split in two.** "Connect WordPress Site" and
"View Analytics" now navigate to real destinations; "New Post" and
"Generate AI Draft" stay genuinely disabled, since neither has a real
target yet (no post-creation UI, no AI backend). `mockQuickActions`
moved out of the now-deleted `services/mock/` into the component
itself — it was always static UI configuration, not simulated API
data, and didn't belong under a "mock service" label.

**Zero accessibility regressions.** An `axe-core` pass against both
pages carrying entirely new real-data content (`/dashboard`,
`/settings`) returned zero violations.

## Background Job & Queue Platform (Milestone 11)

**Content sync is asynchronous now — the exact seam Milestone 10 named
in advance.** `POST /sites/{site}/sync` dispatches
`SyncWordPressPostsJob` instead of blocking the request; the endpoint
returns `202 Accepted` with `{status: "queued"}` immediately. Full
reasoning, retry/backoff/uniqueness design, and the security review in
`docs/adr/0009-background-job-platform.md`.

**A reusable job pattern, proven by a second consumer, not just
asserted reusable.** `RefreshSiteMetadataJob` shares the same shape
(retries, backoff, per-site uniqueness) as the content-sync job and is
consumed by a new daily Scheduler task refreshing metadata for every
connected site — deliberately *not* wired into the existing manual
"Refresh Metadata" button, since that action is fast and bounded
enough that synchronous, immediate feedback is still the right UX.
Both jobs use Laravel's existing `database` queue driver — configured
since Milestone 1, no new infrastructure dependency.

**System Health's queue metrics are real now.** A new
`QueueHealthChecker` (mirroring the existing `DatabaseHealthChecker`)
reports real `pending`/`failed` counts from the `jobs`/`failed_jobs`
tables and a `degraded` status derived from actual failed-job
presence — verified live in this milestone's own browser verification
against a genuinely failed sync job, not just asserted in tests.

**A verified, not assumed, credential-security property.** Traced
Laravel's `SerializesModels` behavior directly: a job's model
properties are persisted into the queue payload as a class name plus
primary key only, re-fetched fresh on execution. `Site.credential` is
never eager-loaded onto a job's `Site` property, so the encrypted
WordPress Application Password never enters the `jobs` table's
payload column at any point.

**Frontend: polling, not WebSockets — the seam is isolated to two
hooks.** `useSite`/`useSyncStatus` poll every 2 seconds only while the
underlying resource's status is `syncing`, stopping automatically once
it settles. `SyncButton` no longer shows created/updated/skipped
counts (that synchronous response shape no longer exists); the site's
own status badge and `SyncSummary` card reflect live progress instead.
A future real-time push mechanism would only ever touch these two
hooks — no component or backend contract would need to change.

## Known Limitations

- `Card`, `Badge`, and other primitives expose more variants (e.g.
  `ghost`, `link` on `Badge`) than `common/` components currently use —
  intentional; they're general-purpose primitives, and unused variants
  cost nothing until a real use case needs them.
- No automated component/route tests yet (Frontend Testing is
  Milestone 15). Every design-system and shell change is verified
  manually: a real browser (Playwright) across breakpoints/
  interactions, plus an `axe-core` audit — widened as of Milestone 4.1
  to include `best-practice`-tagged rules, not just strict WCAG-tagged
  ones, after the narrower scope missed a real nested-landmark defect
  in Milestone 4 (see `docs/ENGINEERING_JOURNAL.md`) — run against a
  temporary preview (or the real shell routes) and confirmed clean
  before the milestone is called done. Real, repeated, and has caught
  genuine defects every time it's been run — but it's manual, not a CI
  gate, until Milestone 15/18.
- No "skip to content" link ahead of the sidebar navigation. Not
  flagged by `axe-core`'s WCAG 2.4.1 (Bypass Blocks) check — satisfied
  here by proper `<nav>`/`<main>` landmark structure, which assistive
  tech can jump between directly — but a visible skip link is still a
  reasonable future enhancement once real page content (not
  placeholders) makes "long tab order to reach content" a bigger cost.
- ~~`isActive = pathname === item.href` (exact match) in `AppSidebar`~~
  **Resolved, Milestone 9** — `/wordpress/[id]` is the first nested
  route this app has, and `isActive` now matches a full path segment
  prefix (`pathname === item.href || pathname.startsWith(item.href +
  "/")`), not a bare `startsWith`. See
  `docs/adr/0002-product-shell.md`.
- ~~Six of nine dashboard widgets are still mocked~~ **Resolved,
  Milestone 10.1** — every widget now either reads real data or is a
  deliberately, explicitly documented placeholder (Quick Actions'
  two no-target actions; AI Assistant Preview, pending Milestone 14).
  `src/services/mock/` no longer exists. See
  `docs/MILESTONE_REPORT_M10_1.md`.
- AI Assistant Preview has no backend — `Generate` is intentionally
  disabled. Future integration point documented inline in
  `src/features/dashboard/components/ai-assistant-preview.tsx`; no
  "AI Jobs" table exists yet either (deliberately deferred — see
  `docs/adr/0005-domain-model.md`).
- No registration, workspace switcher UI, email verification, password
  reset, 2FA, or social auth (Milestone 8) — every one deliberately
  deferred with its own reasoning, not a gap; see
  `docs/adr/0006-authentication-architecture.md`'s Trade-offs and Future
  IAM Roadmap sections. Login is against `DemoDataSeeder`'s seeded user
  only until a future onboarding milestone adds real registration.
- Sanctum's cookie-session auth requires the frontend and backend to
  share a registrable domain (or configured subdomains) in production —
  works locally out of the box, but the documented Vercel + Railway
  target is two unrelated domains today. Deliberately deferred to
  Milestone 19 (Cloud Deployment & Security Hardening), the same
  pattern as the SQLite→MySQL production decision.
- No repository layer, no real analytics *events* schema (only daily
  snapshots), no pagination on Sites/Posts index endpoints, no
  dedicated workspace-deletion flow, SQLite (not a server database) in
  local development — all deliberate Milestone 6–7 scope decisions,
  not gaps; see both ADRs' Trade-offs sections for the reasoning
  behind each, and `docs/ENGINEERING_JOURNAL.md`'s Future Backlog for
  what each unblocks.
- `wordpress_version` and `php_version` are always `null` on a real
  connection (Milestone 9, by design) — stock WordPress doesn't expose
  either through its public REST API without a companion plugin. See
  `docs/adr/0007-wordpress-integration-architecture.md`'s "Version
  Detection" section.
- The SSRF guard on WordPress connection URLs checks literal IP
  addresses against private/reserved ranges but doesn't resolve
  hostnames to check where they actually point (Milestone 9, by
  design) — a real, named limitation, deferred to Milestone 19 to keep
  the check network-free and deterministic in tests. See the ADR's
  Security section.
- ~~No Content Management, Publishing, or Background Jobs yet~~
  **Partially resolved, Milestone 10** — content *synchronization*
  (reading WordPress posts into WP Studio) is real now; *Publishing*
  (writing WP Studio changes back to WordPress) and Background Jobs
  remain future milestones per `docs/ROADMAP.md`.
- Content sync fetches only posts, only title/status/dates/URL/sync
  metadata — no post body/content is fetched or stored (Milestone 10,
  by design). Storing full content ahead of an actual editing/
  Publishing feature needing it would be speculative scope; see
  `docs/adr/0008-content-synchronization.md`'s Rejected Alternatives.
- ~~Content sync is fully synchronous~~ **Resolved, Milestone 11** —
  `POST .../sync` now dispatches a queued job and returns immediately.
  The 20-page (2,000-post) safety cap itself remains, now as a bound
  on the async job's own execution rather than on a blocking request;
  see `docs/adr/0009-background-job-platform.md`.
- `WordPressPostMapper::upsert()` runs one lookup query per WordPress
  item rather than a batch operation — up to ~100 queries per page at
  today's `per_page=100` (Milestone 10, by design, not yet a measured
  problem at real usage). See the ADR's Performance section.
- ~~System Health's `backgroundQueue` is an honest, hardcoded
  placeholder~~ **Resolved, Milestone 11** — real `pending`/`failed`
  counts and a derived `degraded`/`operational` status, read from the
  actual `jobs`/`failed_jobs` tables.
- Settings is real but read-only — genuine workspace/user data, no
  editable preferences, no `PATCH` endpoint (Milestone 10.1, by
  design). No product decision yet about what a user should be able to
  change; building the form ahead of that decision would be
  speculative scope.
- Sites/Posts index endpoints still have no pagination (named since
  Milestone 7, reviewed again and deliberately deferred in Milestone
  10.1 — real page-size/UI decision still needed, not a reflexive
  default).
- `DashboardService::recentActivity()` runs three separate queries and
  merges them in application code rather than one query against a
  dedicated activity-log table (Milestone 10.1, by design) — no such
  table exists; the feed is derived live from existing `Post`/`Site`
  timestamps. Fine at today's real usage.
- No process supervision keeps `queue:work` running in any environment
  today (Milestone 11, by design) — a real deployment needs Supervisor
  or equivalent; deferred to Milestone 19 (Cloud Deployment & Security
  Hardening).
- `RefreshSiteMetadataJob` is not wired to the existing manual
  "Refresh Metadata" button, which stays synchronous by design
  (Milestone 11) — that action is fast/bounded enough that immediate
  feedback is still the right UX; the job is reused instead by the new
  daily Scheduler task. See `docs/adr/0009-background-job-platform.md`.
- `job_batches` (Laravel's default queue-batching table) is
  provisioned but unused (Milestone 11, by design) — nothing yet
  dispatches a set of jobs needing combined completion tracking; a
  real candidate once a bulk "sync every site in a workspace" action
  exists.

## Status

Milestone 11 (Background Job & Queue Platform) complete. See
`ROADMAP.md` for the full milestone list, `DEVLOG.md` for a running log
of completed work, and `docs/adr/` / `docs/ENGINEERING_JOURNAL.md` for
architectural decisions and the reasoning behind them.
