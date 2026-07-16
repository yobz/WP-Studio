# Milestone 13 Report

## Date

2026-07-16

---

## Objective

Add a GraphQL layer exactly where it adds real value over the existing
REST API — dashboard aggregation queries with variable shape, per
`docs/ROADMAP.md`'s own Milestone 13 entry — without a wholesale
replacement of `/api/v1`. Today's Dashboard makes four independent
REST round-trips for data that's always needed together, on one page,
every time; GraphQL should let the frontend ask for exactly that data
in one request with a client-selectable shape.

---

## Executive Summary

Milestone 13 is complete. This project's ROADMAP entry for Milestone
13 was deliberately terse ("GraphQL where it adds real value... not a
wholesale replacement") rather than a full brief, so the Architecture
Review stage itself did more work than usual this milestone: it
concretely scoped what GraphQL should and shouldn't touch before any
code was written, then verified that scope against the Architecture
Drift Review. The conclusion — a single read-only endpoint backing
exactly the Dashboard's aggregation needs, with every other resource
staying on REST — held throughout implementation with no scope
creep.

`nuwave/lighthouse` (the standard Laravel GraphQL package) backs two
schema-first query resolvers, both thin wrappers delegating to the
exact same `DashboardService`/`AnalyticsService`/`SystemHealthService`
the REST controllers already call — zero duplicated query logic. The
GraphQL route sits behind the identical `auth:sanctum` →
`ResolveCurrentWorkspace` middleware stack every REST route already
uses, registered manually in `routes/api_v1.php` rather than through
Lighthouse's own special-cased top-level route.

Two real, non-obvious defects were caught during this milestone's own
verification, not shipped:

1. A stale `bootstrap/cache/services.php` (the same OneDrive-path
   cache-staleness class of issue first documented in Milestone 6)
   silently prevented Lighthouse's service provider from registering
   at all after `composer require` — caught immediately by checking
   `php artisan route:list` rather than assuming installation alone
   was sufficient.
2. GraphQL enum fields serialize over the wire as their **schema
   name** (e.g. `POST_PUBLISHED`), not the `@enum(value: ...)`
   directive's internal PHP value (`post-published`) — a real,
   easy-to-miss GraphQL semantics gap that broke `RecentActivity`'s
   icon lookup in a live browser check (a React "element type is
   invalid" crash), caught by verifying in an actual browser rather
   than trusting `typecheck`/`lint`/`build` alone, all of which passed
   cleanly on the broken code.

Every dashboard widget component required **zero** changes — the same
"swap the hook's data source, not the component" discipline this
project has followed since Milestone 6's first mock-to-real migration,
now demonstrated across a transport change (REST → GraphQL) rather
than just a data-source change (mock → real).

---

## Architecture Review

Read the relevant existing services (`DashboardService`,
`AnalyticsService`, `SystemHealthService` and their DTOs),
`DashboardController`/`AnalyticsController` for the REST contract
being supplemented, `config/cors.php`, `bootstrap/app.php`'s
`statefulApi()` middleware wiring, and the frontend's four dashboard
REST-consuming hooks and their mappers, before writing any schema or
resolver code — confirming exactly what data shape GraphQL needed to
produce and where the existing Service Layer boundary already sat.

---

## Architecture Drift Review

**No duplicate services or query logic.** Both GraphQL resolvers call
`DashboardService`/`AnalyticsService`/`SystemHealthService` directly —
the same methods, same DTOs, same authorization/tenant-scoping
dependency (`CurrentWorkspaceContext`) the REST controllers already
use. No new aggregation logic exists anywhere in this milestone.

**No overlapping responsibility with existing auth/tenancy
infrastructure.** The GraphQL route was deliberately registered inside
the existing `auth:sanctum` → `ResolveCurrentWorkspace` middleware
group in `routes/api_v1.php`, not through Lighthouse's own automatic
route registration (disabled via `'route' => false` in
`config/lighthouse.php`) — avoiding a second, parallel
authentication/tenant-resolution mechanism existing alongside the
proven one.

**Scope held under real pressure to expand.** Lighthouse's own
directives (`@all`, `@find`, relationship resolvers) make it easy to
expose `Site`/`Post` as full GraphQL types with minimal code — reviewed
and explicitly rejected for this milestone. Those resources already
have complete, tested, Policy-enforced REST CRUD; a second read (or
write) path for the same data would be exactly the kind of duplicated
capability this review step exists to catch.

**Result:** implementation matched the reviewed scope exactly — one
schema, two query resolvers, zero mutations, zero changes to any
existing REST controller or route.

---

## GraphQL Design

```
POST /api/v1/graphql
  dashboardOverview: DashboardOverview!  — summary + recentActivity + systemHealth
  analyticsPreview(range: AnalyticsRange!): [AnalyticsPoint!]!
```

`app/GraphQL/Queries/DashboardOverview.php` and `AnalyticsPreview.php`
— plain, container-resolved `__invoke()` classes following Lighthouse's
default resolver-class convention, constructor-injecting the same
services their REST-controller equivalents already inject. `AnalyticsRange`
and `ActivityType` are real GraphQL enums, not bare strings — an
invalid value is a schema-level validation error, verified directly by
a test asserting a malformed `range` argument produces a GraphQL
`errors` array with `data: null`, never reaching resolver code.

---

## Frontend Integration

`src/lib/graphql-client.ts` — `graphqlFetch()`, sharing
`api-client.ts`'s CSRF-cookie handshake (extracted into a reusable
`csrfHeader()` helper) and handling both the GraphQL `{data, errors}`
envelope and the REST error envelope a pre-execution `auth:sanctum`
rejection returns, dispatching the same `UNAUTHORIZED_EVENT` either
way. `useDashboardOverview()` is the one hook that calls
`dashboardOverview`; `useKpis()`/`useRecentActivity()`/`useSystemHealth()`
each derive their own shape from it via TanStack Query's `select`,
sharing one cached network request across three widgets.
`useAnalyticsPreview(range)` stays a separate query, since its `range`
argument varies independently of the other three widgets' data.

Every dashboard widget component (`KpiCards`, `RecentActivity`,
`SystemHealth`, `AnalyticsPreview`) required zero changes. The
REST-only frontend service/mapper files these hooks previously used
(`dashboard.service.ts`, `analytics.service.ts`, `system-health.service.ts`,
`map-activity.ts`, `map-analytics-points.ts`) were deleted as genuinely
unused code — the backend REST endpoints they called remain fully
intact, tested, and available to any other consumer; only the
now-unused frontend bindings were removed.

---

## Validation

- `php artisan test`: **127/127 passing** (up from 120) — 7 new tests
  in `tests/Feature/GraphQLDashboardTest.php` covering authentication
  rejection (the standard REST error envelope, verified even for the
  GraphQL route), real aggregated data across all three
  `dashboardOverview` sub-objects in one request, workspace isolation,
  `analyticsPreview`'s `range` argument (both explicit and its schema
  default), a real (not placeholder) queue-metrics value, and
  schema-level rejection of an invalid enum argument.
- `./vendor/bin/pint --dirty`: clean.
- `php artisan lighthouse:validate-schema`: valid.
- `npm run typecheck` / `npm run lint` / `npm run build`: all pass.
- Live browser verification (real `php artisan serve` + `npm run start`
  production build, not `npm run dev`): logged in, confirmed via
  network-request interception that the Dashboard fires **exactly two**
  `POST /api/v1/graphql` requests on load (`DashboardOverview` +
  `AnalyticsPreview`) and **zero** legacy REST dashboard/analytics/
  system-health requests; confirmed switching the Analytics Preview
  range control fires exactly one additional GraphQL request without
  refetching the other three widgets; confirmed real rendered values
  (KPI counts, Recent Activity items with correct icons, System Health
  status, a real non-empty Analytics chart with genuine visitor
  data verified via a direct authenticated GraphQL query) — all
  against the real backend and real seeded data, zero console errors.
- `axe-core`: **zero violations** on the GraphQL-backed Dashboard.

**Two real defects found and fixed during this validation, not
before.** See Executive Summary. Both are documented in
`docs/ENGINEERING_JOURNAL.md`'s dated entries with full investigation
detail, since both are genuinely non-obvious and likely to recur on
this project or a similar one.

---

## Production Readiness

The GraphQL layer is genuinely additive: it introduces no new write
surface, reuses the exact same authorization/tenant-scoping
infrastructure as every REST route, and duplicates no aggregation
logic (every resolver is a thin pass-through to an already-tested
service method). The one new operational dependency is
`nuwave/lighthouse` itself — actively maintained, Laravel
12-compatible, no additional runtime/service to deploy. Schema caching
is already configured to follow `APP_ENV` (disabled in `local`,
enabled elsewhere) via Lighthouse's own default, matching this
project's existing environment-aware config pattern.

---

## Technical Debt Resolved

- **The Dashboard's four-separate-REST-round-trips pattern**, an
  accepted-but-unoptimized shape since Milestone 10.1 (each widget
  migrated to real data independently, each keeping its own request),
  is resolved for the aggregation-shaped widgets specifically —
  `KpiCards`, `RecentActivity`, and `SystemHealth` now share one
  request; `AnalyticsPreview` is a second, deliberately independent
  one.
- **Five now-unused REST-only frontend files** (three services, two
  mappers) removed as genuine dead code, not left behind.

---

## Deferred Work

- **GraphQL mutations** — not built. Every write in this application
  still goes through REST, deliberately; no product need has emerged
  for a GraphQL write path.
- **A broader GraphQL schema over Sites/Posts/Media** — reviewed and
  explicitly rejected this milestone (see Architecture Drift Review).
  REST already serves these completely; a real future candidate only
  if a genuine aggregation/variable-shape need emerges for one of them
  the way it did for the Dashboard.
- **Persisted queries** — Lighthouse supports Automatic Persisted
  Queries out of the box and it's already enabled in config; not
  exercised by the frontend this milestone, available without further
  backend work whenever needed.
- **GraphQL subscriptions** — not built; no real-time GraphQL need
  exists yet (Milestone 11's polling pattern already covers the one
  real-time-ish need this project has).

---

## Risks

- **A second query language now exists in the codebase** — a real,
  ongoing cost (two things to understand instead of one) accepted
  deliberately because the aggregation use case it solves is real and
  named by the roadmap, not spec work. Scope discipline (documented
  above) is what keeps this cost bounded rather than growing.
- **GraphQL enum wire-format semantics are a real, documented trap**
  (see Engineering Journal) — any future schema addition using
  `@enum(value:...)` needs the same wire-name-vs-internal-value
  translation this milestone had to add for `ActivityType`; not doing
  so silently reintroduces the exact bug this milestone found and
  fixed.
- **No rate limiting specific to the GraphQL endpoint** — it inherits
  no dedicated throttle the way `wordpress-connection` or
  `media-upload` do. Acceptable for now (read-only, no external calls,
  no expensive aggregation beyond what REST already allows
  unthrottled), but worth a look if this schema ever grows to include
  anything expensive.

---

## Recommendation for Milestone 14

Per `docs/ROADMAP.md`, Milestone 14 (AI-Assisted Content Generation) is
next in sequence. This milestone's GraphQL layer is available as a
pattern (not a requirement) if AI-generated content's own data shape
benefits from client-selectable fields the way dashboard aggregation
did — but Milestone 14's own scope should decide that on its own
merits, not by default. Waiting for explicit approval before starting,
per this milestone's own stop condition.
