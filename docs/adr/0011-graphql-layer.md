# 0011 — GraphQL Layer

**Status:** Accepted (Milestone 13)

## Decision

Add a single, read-only GraphQL endpoint (`POST /api/v1/graphql`, via
`nuwave/lighthouse`) backing exactly the case named in
`docs/ROADMAP.md`'s own Milestone 13 entry: dashboard aggregation with
variable shape. Today's Dashboard makes four independent REST
round-trips (`GET /dashboard/summary`, `GET /dashboard/activity`,
`GET /analytics`, `GET /system-health`) to render one page. GraphQL
lets the frontend fetch the same data in two requests — one
`dashboardOverview` query composing summary, recent activity, and
system health; one `analyticsPreview(range:)` query for the
independently-variable analytics chart — with a client-selectable
field shape. This is **not** a wholesale replacement of `/api/v1/*`:
no mutations exist, and Sites/Posts/Media/WordPress sync/background
jobs keep their existing REST endpoints entirely unchanged. GraphQL
resolvers are thin — they call the exact same `DashboardService`/
`AnalyticsService`/`SystemHealthService` the REST controllers already
call, so no query/aggregation logic is duplicated between the two
transports.

## Context

**Where this sits in the project.** Every dashboard widget was
migrated off mock data across Milestones 6, 7, and 10.1 — each reading
its own REST endpoint. That was the right sequence (prove one real
endpoint before building a second transport on top of it), but it
left the Dashboard's own page-load behavior exactly what
`docs/ROADMAP.md`'s Milestone 13 entry names as GraphQL's opportunity:
several small, independently-loading REST calls for data that's
always requested together, on one page, every time.

**Architecture Drift Review.** Checked whether a GraphQL layer would
duplicate anything already built. It doesn't: `DashboardService`,
`AnalyticsService`, and `SystemHealthService` are read exactly as-is
by the new resolvers — zero new query logic, only a new way to call
existing methods. No overlapping responsibility with
`CurrentWorkspaceContext`/`ResolveCurrentWorkspace` either — the
GraphQL route sits behind the identical middleware stack every REST
route already uses, rather than inventing a parallel
authentication/tenant-resolution mechanism. `App\Services\WordPress\`
was reviewed as this project's established template for "a new
transport/integration should follow one contract, minimal new
surface" — matched here by keeping the GraphQL layer to two resolver
classes with zero new service-layer code.

## Alternatives Considered

**Which GraphQL server package.** `nuwave/lighthouse` (schema-first,
the de facto standard for Laravel — actively maintained, Laravel
12-compatible) vs. hand-rolling a resolver against `webonyx/graphql-php`
directly, vs. a Node-side GraphQL gateway (e.g. Apollo Server)
composing the existing REST API. Chosen Lighthouse: it integrates
directly with Laravel's container (constructor-injected resolvers,
exactly like every existing controller/service), needs no second
runtime or deployment target (a Node gateway would), and its
schema-first `.graphql` file is a real, versionable contract —
consistent with this project's existing "one contract, one typed
surface" pattern from the WordPress integration layer
(`docs/adr/0007-wordpress-integration-architecture.md`).

**Scope: dashboard aggregation only, not a general-purpose graph over
every model.** Considered exposing `Site`/`Post` as GraphQL types too,
since Lighthouse makes `@all`/`@find`/relationship directives
low-effort. Rejected: the brief is explicit ("not a wholesale
replacement"), and Sites/Posts/Media already have full, tested,
policy-enforced REST CRUD — building a second, parallel read (or
worse, write) path for the same resources would be exactly the kind
of duplicated capability the Architecture Drift Review exists to
catch, not a case it should wave through. GraphQL earns its place here
specifically because the Dashboard's *aggregation* shape is a genuine
gap REST's one-resource-per-endpoint convention doesn't serve well —
that gap doesn't exist for Sites/Posts, which are already single,
well-shaped resources.

**Manual route registration vs. Lighthouse's automatic `/graphql`
route.** Lighthouse registers its own top-level route by default,
outside `routes/api_v1.php`'s existing `Route::prefix('v1')` structure
and its own middleware group. Disabled that (`'route' => false` in
`config/lighthouse.php`) and registered `POST /api/v1/graphql`
manually, inside the exact same `auth:sanctum` →
`ResolveCurrentWorkspace` group every REST route already sits in.
Costs one extra line of config; buys a single, consistent middleware
stack and URL versioning scheme instead of a special-cased endpoint
with its own rules.

**No mutations this milestone.** GraphQL mutations would need the
same authorization/validation rigor every REST write path already has
(Form Requests, Policies) rebuilt in a second shape — real work with
no named need behind it yet. Every write in this application still
goes through REST, deliberately.

## Resolver Design

```
app/GraphQL/Queries/
├── DashboardOverview.php   — dashboardOverview: DashboardOverview!
└── AnalyticsPreview.php    — analyticsPreview(range): [AnalyticsPoint!]!
```

Both are plain, container-resolved classes with an `__invoke()`
method — Lighthouse's default resolver convention (`Str::studly($fieldName)`
against the configured `queries` namespace), not a directive-annotated
field. Each constructor-injects the exact service(s) the equivalent
REST controller already injects (`DashboardService`,
`SystemHealthService`, `AnalyticsService`) plus
`CurrentWorkspaceContext` — the same tenant-scoping dependency every
workspace-scoped REST controller already requires. `DashboardOverview::__invoke()`
returns a plain array of three DTOs (`DashboardSummaryData`,
`ActivityItemData[]`, `SystemHealthData`); Lighthouse's default field
resolution reads GraphQL field names directly off each DTO's public
properties, which already use the same camelCase naming GraphQL
expects — no field-level resolvers or mapping layer were needed
beyond the two root query classes.

## Schema

```graphql
type Query {
    dashboardOverview: DashboardOverview!
    analyticsPreview(range: AnalyticsRange! = SEVEN_D): [AnalyticsPoint!]!
}

type DashboardOverview {
    summary: DashboardSummary!
    recentActivity: [ActivityItem!]!
    systemHealth: SystemHealth!
}
```

`AnalyticsRange` and `ActivityType` are real GraphQL enums
(`@enum(value: "7d")` etc.) mapping the schema's PascalCase/SNAKE_CASE
names onto the exact internal string values `AnalyticsService`/
`ActivityItemData` already use — chosen over plain `String` fields so
an invalid range/type is a schema-level validation error (caught
before a resolver ever runs), not a silently-ignored or crashing
string comparison.

## Frontend

`src/lib/graphql-client.ts` — `graphqlFetch()`, the GraphQL sibling to
`apiFetch()`/`apiUpload()`, sharing the exact same CSRF-cookie
handshake (`csrfHeader()`, extracted from `api-client.ts` for reuse
across all three) since the GraphQL endpoint sits behind identical
Sanctum session auth. Because `auth:sanctum` rejects an unauthenticated
request *before* GraphQL ever executes, an expired session returns the
standard REST error envelope, not `{data, errors}` — `graphqlFetch()`
handles both shapes explicitly and dispatches the same
`UNAUTHORIZED_EVENT` `apiFetch()` already does, so a session expiry
behaves identically regardless of which transport a component happens
to use.

`useDashboardOverview()` (`src/features/dashboard/hooks/use-dashboard-overview.ts`)
is the one hook that calls `dashboardOverview`; `useKpis()`,
`useRecentActivity()`, and `useSystemHealth()` each call it with their
own `select` function, deriving an independent, memoized shape from
one shared TanStack Query cache entry — three widgets, one network
request. `useAnalyticsPreview(range)` is a second, independent query
(its own `range` variable means re-fetching it shouldn't re-fetch the
other three widgets too). Every existing widget component
(`KpiCards`, `RecentActivity`, `SystemHealth`, `AnalyticsPreview`)
needed **zero** changes — the same "swap the hook's data source, not
the component" pattern this project has followed since Milestone 6's
first mock-to-real migration.

**The REST-only frontend service/mapper files these hooks used are
deleted, not left as dead code:** `src/services/api/dashboard.service.ts`,
`analytics.service.ts`, `system-health.service.ts`,
`map-activity.ts`, `map-analytics-points.ts`. Deliberately asymmetric
with the backend: the REST *endpoints* stay fully intact, tested, and
documented (a real, independently-valuable API contract regardless of
what this particular frontend calls) — but a frontend wrapper function
nothing calls anymore is genuinely unused code by this project's own
standard, the same reasoning that deleted `SyncResultResource` in
Milestone 11 once its only caller was gone.

## Security

- **Identical middleware stack, not a parallel one.** `auth:sanctum` +
  `ResolveCurrentWorkspace` gate the GraphQL route exactly as they gate
  every REST route — an unauthenticated GraphQL request never reaches
  resolver code, the same guarantee REST already has.
- **No client-supplied workspace ID.** Both resolvers call
  `CurrentWorkspaceContext::get()`, the same tenant-scoping dependency
  every workspace-scoped REST controller uses — never a `workspace_id`
  argument a client could set. Verified directly: a test creates data
  in a second workspace and confirms `dashboardOverview` never
  reflects it.
- **No new write surface.** No mutations exist in this schema at all
  — the entire security surface this milestone adds is three read
  queries, each already gated by the same auth/tenant stack as their
  REST equivalents.
- **Introspection left enabled** (Lighthouse's default) — this is an
  authenticated-only endpoint behind the same session auth as every
  other route, not a public API; introspection aids the one real
  audience (this project's own frontend development), and disabling it
  provides no real defense against an authenticated attacker who can
  already read the open-source schema file in this repository.

## Performance

- **Fewer round-trips, not more queries.** `dashboardOverview` still
  runs the exact same set of underlying Eloquent queries
  `DashboardService`/`SystemHealthService` already ran across two
  separate REST requests — consolidated into one HTTP round-trip
  instead of two, with zero new N+1 risk since no new relationship
  traversal was added.
- **No DataLoader/batching complexity needed.** Lighthouse's batching
  features exist for resolving GraphQL relationships across many rows
  (e.g. `Post.site` for a list of posts). This schema has no such
  relationship fields — every resolver returns already-composed DTOs
  from existing services, not raw Eloquent models a client traverses
  field-by-field — so there is no N+1 surface for a DataLoader to
  solve in the first place.
- **Verified in a live browser, not just asserted:** confirmed exactly
  two `POST /api/v1/graphql` requests fire on Dashboard load (down
  from four separate REST requests previously), and exactly one
  additional request fires when the Analytics Preview range control
  changes — the other three widgets' cached data is untouched.

## Rejected Alternatives

**A generic, whole-app GraphQL schema replacing REST.** Rejected
outright per the brief's own instruction and this project's
established "extend, don't duplicate" discipline — REST already
serves Sites/Posts/Media/WordPress sync/Media/background jobs
completely, with full Policy/Form Request/rate-limiting coverage.
Rebuilding that in GraphQL would be a second implementation of
already-solved problems, not new value.

**Apollo Client / urql on the frontend.** Considered for cache
normalization and devtools. Rejected as unnecessary for two read-only
queries with no relationships to normalize — TanStack Query (already
this project's server-state library since Milestone 5) does everything
this milestone's actual GraphQL usage needs (caching, `select`-based
derivation, retry/refetch), and adding a second data-fetching library
for two queries would be real complexity with no corresponding need.

## Future Evolution

- **A future GraphQL subscription or a broader query surface** would
  be a genuine, separate decision if a real need for it emerges (e.g.
  a future notifications feature wanting push-style updates) — not
  built speculatively now.
- **Milestone 14 (AI-Assisted Content Generation)** could expose an
  `aiSuggestions`-shaped query the same way `dashboardOverview` does
  today, if the AI feature's own data shape turns out to benefit from
  client-selectable fields the way dashboard aggregation did.
- **Persisted queries** (Lighthouse supports Automatic Persisted
  Queries out of the box, already enabled in config) are available
  without further backend work whenever a future performance pass
  wants them.
