# 0004 — Backend Foundation (Laravel)

**Status:** Accepted (Milestone 6)

## Decision

Stand up a Laravel 12 backend in a dedicated `backend/` directory,
entirely separate from the Next.js app; version the API from day one
(`/api/v1`); wrap every response in a consistent JSON envelope; centralize
exception handling so every error — validation, not-found, unhandled —
renders through the same shape; lay observability and security
groundwork (request IDs, structured log context, CORS, secure headers)
before any real business logic exists; and migrate exactly one
frontend widget (KPI Cards) off the mock service layer to prove the
whole chain works, leaving the other eight on mocks.

## Context

**Where this sits in the project.** Milestones 1–5 built a complete,
production-quality frontend — Design System, Product Shell, Dashboard
Experience — entirely on a mock service layer (`src/services/mock/`,
see [[0003-dashboard-data-architecture]](0003-dashboard-data-architecture.md)).
That mock layer was deliberately shaped like a future REST response
from the start. This milestone is where that bet gets tested: does a
real backend actually slot in without rewriting the widgets built on
top of the mock?

**What this milestone is not.** No authentication (Milestone 8, though
Sanctum-readiness is prepared for), no WordPress integration, no AI,
no real analytics schema, no production business logic. The brief is
explicit: architecture, not business logic. Every domain endpoint
besides Dashboard summary is a placeholder returning a 200 with
minimal or empty data — proving the route/versioning/envelope pattern
exists for all six domains without inventing premature business rules
for the five that don't need them yet.

## Alternatives Considered

**Backend location — `backend/` subdirectory vs. a separate repo.**
A separate repository is more common for a real two-service
architecture, but this project's own `docs/PROJECT.md` already
documents a monorepo-style layout, and the brief explicitly calls for
`backend/` inside the existing repo. Chosen: `backend/`, fully
self-contained (its own `composer.json`, `.env`, `vendor/`, `.gitignore`)
so it could be extracted into its own repository later with `git
subtree`/`git filter-repo` if that ever becomes necessary — nothing
about this milestone's structure assumes the monorepo layout is
permanent.

**Database — SQLite vs. PostgreSQL/MySQL for local development.** The
brief explicitly offered both. Chosen SQLite: zero local service to
install/run/configure (a single file, `database/database.sqlite`),
which matches this milestone's own "architecture, not business logic"
scope — nothing here needs Postgres-specific features (JSONB,
full-text search, etc.) yet. `config/database.php`'s `sqlite`
connection is Laravel's default; switching to Postgres later is a
`DB_CONNECTION` env change plus a driver install, not a schema
rewrite — migrations use Laravel's schema builder, not raw SQL, so
they're already portable.

**Repositories — built vs. deliberately omitted.** The brief listed
Repositories as "only if justified." `DashboardService::summary()`
runs two straightforward Eloquent aggregate queries against `Site`/
`Post` — no swappable data source, no complex query composition, no
current second consumer of the same query logic. A repository layer
here would be indirection with nothing to abstract yet. Not built.
Revisit if a future milestone introduces a genuine need (e.g. a WordPress
REST API-backed `Site` alongside a database-backed one, needing a
common interface).

**DTOs — used for the one real endpoint.** `DashboardSummaryData` is a
plain `readonly` class carrying `DashboardService`'s output to
`DashboardSummaryResource`. Considered skipping it (the service could
return a plain array), but a DTO means the shape is checked by PHP's
type system at the service/resource boundary, not just by convention —
worth the one extra class for the one endpoint that has real
aggregation logic. Not used for the placeholder endpoints, which have
no real data to shape yet.

**API response envelope — Laravel's default Resource wrapping vs. a
custom `ApiResponse` envelope.** Laravel's default (`{"data": ...}`
via `JsonResource`, automatic when a Resource is a controller's return
value) doesn't have a place for a consistent error shape, a `success`
flag, or a `request_id`. Built `App\Http\Support\ApiResponse` as the
single place defining both success and error envelopes:

```json
// Success
{ "success": true, "data": { ... }, "meta"?: { ... } }

// Error
{ "success": false, "error": { "code": "...", "message": "...", "details"?: {...} }, "request_id"?: "..." }
```

Every controller calls `ApiResponse::success()`; every exception —
validation failures, 404s, unhandled throwables — renders through
`ApiExceptionHandler`, which calls `ApiResponse::error()`. A frontend
consumer (`src/lib/api-client.ts`) only ever needs to branch on one
shape, regardless of which endpoint or which failure mode.

**API versioning — path prefix vs. header-based.** `/api/v1/...` (path
prefix) over an `Accept`-header version scheme: simpler to route,
simpler to test with `curl`, simpler for the frontend (no custom
header plumbing through every fetch call), and the brief's own example
was already path-based. `routes/api.php` only composes versions
(`Route::prefix('v1')->group(base_path('routes/api_v1.php'))`) — it
never defines a route directly, so a future `/api/v2` is a new file
plus one new line, not a rewrite of `api.php`.

**Exception handling — Laravel 11+'s `withExceptions()` closure vs. a
classic `app/Exceptions/Handler.php`.** Laravel 12's skeleton no longer
ships a `Handler.php` by default — `bootstrap/app.php`'s
`withExceptions()` is the current idiomatic location. Extracted the
actual logic into `App\Exceptions\ApiExceptionHandler::register()`
(a static method called from `bootstrap/app.php`) rather than inlining
a large closure there, so `bootstrap/app.php` stays a thin composition
file and the mapping logic is unit-testable/readable on its own.

**Migration strategy — all nine widgets at once vs. exactly one.** The
brief was explicit: migrate Dashboard summary only, leave the rest on
mocks. This is also the more honest demonstration — migrating every
widget in one pass wouldn't prove the mock layer's shape was actually
sufficient on its own merits versus being adjusted at the same time as
the backend was designed to match it. KPI Cards was chosen as the one
because it's the widget with the most naturally "aggregate query"-shaped
data (counts and sums), the clearest real analogue in the new `sites`/
`posts` tables, and zero dependency on any other widget's data.

## Chosen Solution

**Directory structure** (`backend/app/`):
- `Http/Controllers/Api/V1/` — one controller per domain
  (`DashboardController` real; `SiteController`, `PostController`,
  `AnalyticsController`, `AiController`, `SettingsController`
  placeholders).
- `Http/Resources/V1/` — `DashboardSummaryResource`, the one Resource
  this milestone needs.
- `Http/Support/ApiResponse.php` — the envelope, described above.
- `Http/Middleware/` — `AssignRequestId`, `SecureHeaders` (both
  described under Observability/Security below).
- `Services/` — `DashboardService`, the one real business-logic class.
- `DTOs/` — `DashboardSummaryData`.
- `Enums/` — `SiteStatus`, `PostStatus` (native PHP 8.1+ backed enums,
  cast on the Eloquent models via `casts()`).
- `Exceptions/` — `ApiException` (base for app-thrown exceptions),
  `ServiceUnavailableException` (one concrete example, unused today,
  ready for the first external-service integration), `ApiExceptionHandler`.
- `Policies/` — `SitePolicy`, placeholder, deny-by-default (see
  Security below).
- `Events/` + `Listeners/` — `SiteConnected` + `LogSiteConnected`,
  placeholder, not dispatched anywhere yet — establishes the pattern
  before Milestone 9's real "connect a site" flow needs it.
- `Models/` — `Site`, `Post`, both with explicit `$fillable` (never
  `$guarded = []`) and enum casts.

**Routing** (`routes/`): `api.php` composes versions;
`api_v1.php` holds the actual v1 route definitions. No `auth`
middleware anywhere yet — every route is open, by design, until
Milestone 8.

**Database**: two foundational tables, `sites` and `posts` — chosen
because they're exactly the two domains (WordPress Sites, Posts) the
brief named as needing placeholder endpoints, and because they're what
`DashboardService` needs to compute real KPIs. `sites.monthly_visitors`
is a denormalized integer column, not a time-series table — a full
analytics events schema is explicitly future-milestone scope (see
Trade-offs). Seeded via `SiteSeeder` with data shaped to resemble the
frontend's own mock fixtures, so the real endpoint's numbers look
plausible next to what the other eight (still-mocked) widgets show.

**Observability groundwork**:
- `AssignRequestId` middleware — generates or forwards an
  `X-Request-Id` (reused from the client's own header if present),
  pushes it into Laravel's log context (`Log::shareContext`) so every
  log line during the request carries it automatically, and echoes it
  back on the response header and in error envelopes.
- `/api/v1/health` — checks the actual database connection (not just
  "the PHP process is alive"), separate from Laravel's own built-in
  `/up` (which stays for infrastructure-level probes).
- ~~Sentry and OpenTelemetry are **not** integrated~~ **Sentry
  resolved, Milestone 18** — `sentry/sentry-laravel`, DSN-optional,
  wired via `bootstrap/app.php`'s `withExceptions()` closure exactly as
  predicted here. OpenTelemetry remains a deliberate, documented scope
  cut — no trace-collection backend exists in this project's
  deployment story. See `docs/adr/0016-observability.md`.

**Security groundwork**:
- `config/cors.php` — restricted to `FRONTEND_URLS` (env-configured,
  defaults to `http://localhost:3000`), not the framework's wildcard
  default. `supports_credentials: true` in anticipation of Milestone
  8's Sanctum SPA (cookie-based) auth — inert today, since nothing
  reads a session yet.
- `SecureHeaders` middleware — `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` on every
  response.
- Mass-assignment protection — every model uses explicit `$fillable`.
- CSRF — Laravel's stateless `api.php` routes are unaffected by
  session-based CSRF by default; the future Sanctum SPA flow (Milestone
  8) will need the `sanctum/csrf-cookie` route and its cookie dance,
  which `config/cors.php`'s `paths` array already includes in
  preparation.
- `SitePolicy` — deny-by-default (`false` from every method), not
  because authorization is implemented, but so the *pattern* exists
  and the default is safe the moment a real user model needs it.

**Error handling**: `ApiExceptionHandler::register()` maps
`ValidationException` → 422, `AuthenticationException` → 401,
`AuthorizationException` → 403, `ModelNotFoundException`/
`NotFoundHttpException` → 404, `TooManyRequestsHttpException` → 429,
any `ApiException` → its own declared status/code, any other
`HttpExceptionInterface` → its status, and everything else → 500 with
a generic message unless `APP_DEBUG` is true. Scoped to API requests
only (`is('api/*')` or `expectsJson()`) — the framework's default
`/` welcome page keeps normal HTML error rendering.

**Testing**: Pest 3, installed via `composer require` (not `laravel
new --pest`, since the project already existed) — `tests/Pest.php`
binds `RefreshDatabase` for `Feature` tests. One test file,
`DashboardSummaryTest.php`, four cases: envelope shape, correct
aggregation against seeded data (including that a disconnected site's
data is excluded), the zero-data case, and the request-ID header.
Deliberately not "full coverage" — the brief's own scope.

**Frontend integration**:
- `src/lib/api-client.ts` — the one place that calls `fetch()` against
  the Laravel API and unwraps the envelope, throwing a typed
  `ApiError` on failure.
- `src/services/api/dashboard.service.ts` — `getDashboardSummary()`,
  parallel in shape to the mock service files, returning the API's
  raw numeric `DashboardSummary`.
- `src/features/dashboard/utils/map-summary-to-kpis.ts` — adapts the
  raw API shape into the exact `Kpi[]` shape `KpiCards` already
  consumes. This is the actual point of the exercise: `KpiCards`
  (`src/features/dashboard/components/kpi-cards.tsx`) required **zero**
  changes. Only `use-kpis.ts` changed, from calling the mock's
  `getKpis()` to calling `getDashboardSummary()` + the mapper.
  `trend` is omitted on every real KPI — a single snapshot endpoint
  has no historical comparison point to compute one from; `Kpi.trend`
  was already optional for exactly this reason.
- The now-unused `getKpis()`/`mockKpis` were removed from the mock
  service layer (not left as dead code) — same reasoning as the M5
  finding this milestone also closed for `getQuickActions()`.

## Trade-offs

- No repository layer, per the "Alternatives Considered" reasoning
  above — accepted; revisit only when a real abstraction need appears,
  not preemptively.
- `sites.monthly_visitors` is a denormalized snapshot column, not a
  real analytics/events schema — accepted for this milestone; a
  proper time-series design is explicitly the future Analytics
  milestone's job, not something to guess at here.
- No auth on any route yet, including the placeholder domain
  endpoints — by explicit instruction. Every route is currently
  world-readable; this is fine for local development against seeded
  demo data and is called out here so it's not mistaken for an
  oversight once a real deployment target exists.
- SQLite in local development is not what a production deployment
  would likely run — accepted; the migration-based schema and
  Eloquent query layer are DB-agnostic, so this is a config change,
  not a rewrite, when a real environment needs it.
- Only one of nine dashboard widgets is migrated — by design, not
  partial completion. The other eight's migration is future-milestone
  work, following the exact same pattern this one established.

## Future Implications

- **Migrating another widget** means: add a real controller action
  (or extend an existing placeholder), a service method, a DTO if the
  data has real shape, a Resource, and on the frontend, a
  `src/services/api/*.ts` function plus (if the shapes don't already
  match) a small mapper — then swap one hook's `queryFn`. No widget
  component should ever need to change, the same way `kpi-cards.tsx`
  didn't.
- **Milestone 8 (Authentication)**: `config/cors.php`'s
  `supports_credentials` and `sanctum/csrf-cookie` path, `SitePolicy`'s
  deny-by-default posture, and the already-seeded `User` model are all
  waiting for this milestone specifically. Routes will need an
  `auth:sanctum` group added in `routes/api_v1.php` — one file, one
  wrapping group, not a per-route change.
- **Milestone 9 (WordPress Integration)**: `SiteController`,
  `SiteConnected`/`LogSiteConnected`, and the `sites` table are the
  starting point. Real WordPress REST API calls are exactly where
  `ServiceUnavailableException` (built, unused today) becomes real.
- **Future Analytics milestone**: `sites.monthly_visitors` likely gets
  replaced (or supplemented) by a real events/aggregation schema;
  `AnalyticsController` is already routed and ready to point at it.
- ~~**Sentry/OpenTelemetry**: env placeholders and the single
  `ApiExceptionHandler::render()` choke point are the documented
  starting point~~ — Sentry resolved, Milestone 18; see Observability
  above.
