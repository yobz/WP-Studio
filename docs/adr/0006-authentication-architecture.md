# 0006 — Authentication Architecture

**Status:** Accepted (Milestone 8)

## Decision

Authenticate the SPA against the Laravel API using Laravel Sanctum's
**cookie/session mode** — no JWTs, no personal access tokens issued or
accepted. Introduce a **Current Workspace Resolver** architecture
(`CurrentWorkspaceResolver` → `ResolveCurrentWorkspace` middleware →
`CurrentWorkspaceContext`) so controllers and services depend on "the
workspace this request operates on" as a resolved, authorized value,
never a client-supplied `workspace_id` trusted at face value. Fix two
real vulnerabilities this resolution work surfaced — a cross-tenant
data leak in `DashboardService` and unauthorized `workspace_id`/`site_id`
filters on the Sites/Posts index endpoints — as part of this milestone,
not deferred. Keep the authenticated user as TanStack Query server
state; no Zustand auth store. Defer registration, workspace switching
UI, email verification, password reset, 2FA, and social auth —
named explicitly below, not silently dropped.

## Context

**Where this sits in the project.** Milestone 7 built a real,
tested authorization model (`SitePolicy`/`PostPolicy`, workspace
roles) with nothing to authenticate against — every route was open by
design (`docs/adr/0005-domain-model.md`). This milestone is where that
model becomes load-bearing. The architecture review ahead of
implementation (see the milestone's own approval thread) surfaced that
"add `auth:sanctum`" alone is insufficient: `IndexSitesRequest`
validated `workspace_id` as *any* existing workspace ID with no
membership check, and `DashboardService::summary()` had zero workspace
scoping at all — both invisible with one seeded workspace, both real
cross-tenant leaks the moment a second one exists.

**The architecture amendment.** The initial review proposed a simpler
"every workspace-scoped endpoint takes an explicit `workspace_id` query
param, authorized inline." Revised, on explicit direction, to a
centralized resolver: the frontend should never be responsible for
remembering to send `workspace_id` on every request, and the resolution
*strategy* should be swappable without rewriting every controller.
That's the Current Workspace Resolver below.

## Alternatives Considered

**Cookie/session auth vs. JWT vs. Sanctum personal access tokens.**
Rejected JWT: it's the wrong tool for a first-party SPA talking to its
own API on a shared/cookie-capable domain — a JWT has to live somewhere
JavaScript can attach it to a request, which means `localStorage` or a
JS-readable cookie, both readable by any script on the page (a
persistent XSS payload can exfiltrate it directly, unlike an `httpOnly`
session cookie, which a script can never read even if one runs).
Rejected Sanctum's personal-access-token mode for the same reason (it's
Sanctum's answer to "a JWT, but Laravel-flavored" — still a bearer
token the frontend must store and attach itself). Chosen: Sanctum's
SPA mode — `httpOnly`, `secure`, `SameSite=lax` session cookies, CSRF
double-submit via `XSRF-TOKEN`. The browser handles storage and
attachment; no application code ever touches a token. This is also what
the brief explicitly specified, not just this ADR's own preference.

**Request-driven `workspace_id` vs. a Current Workspace Resolver.**
The simpler design — every workspace-scoped endpoint requires an
explicit, policy-checked `workspace_id` — works, but pushes a
tenant-scoping responsibility onto every frontend call site and every
future controller, forever. Rejected in favor of centralizing
resolution once, in one place, per the milestone's explicit
architecture amendment (see Context). The full reasoning and shape are
under "Chosen Solution" below.

**Registration — built now vs. deferred.** `docs/adr/0005-domain-model.md`
explicitly deferred `WorkspaceService`/workspace creation as "an
onboarding-flow concern for a future milestone." A real self-registration
endpoint immediately raises "which workspace does a brand-new user land
in?" — auto-creating one on register would be new scope invented to fill
a checklist item, not a decision made on its own merits (naming a
workspace, choosing a slug, deciding whether registration implies
"create a new tenant" or "join an existing one via invite" are all real
product questions with no answer yet). Deferred, matching `PublishingJob`
vs. "AI Jobs" precedent in 0005 — build the generic, well-understood
piece now (login/logout/session against an existing user), defer the
piece that depends on a design decision that hasn't been made
(onboarding). `DemoDataSeeder`'s existing seeded user
(`test@example.com`) is the login target until a future onboarding
milestone adds real registration.

**Auth state — TanStack Query vs. a Zustand store.** `docs/adr/0003-dashboard-data-architecture.md`
already drew this line for every other piece of server data: TanStack
Query owns anything that ultimately comes from the API; Zustand is
reserved for genuinely client-only, cross-cutting UI state (its one
existing store is a notification *count*, not a copy of server data).
The authenticated user is server state — it lives in the `users` table,
`GET /api/v1/user` is its source of truth, and it can change for reasons
the client didn't initiate (session expiry, being logged out elsewhere).
A Zustand store holding a copy of it would be a second cache that can
drift from what the server actually thinks, with no mechanism forcing
them back in sync. `useCurrentUser()` (`useQuery(["auth","user"])`) is
the single source of truth; nothing else stores a parallel copy.

**Eliminating the policy N+1 — eager loading vs. resolving the
workspace once.** `docs/adr/0005-domain-model.md`'s Future Backlog
flagged `SitePolicy`/`PostPolicy`'s per-call `hasMember()` query as a
risk the moment `can:view` runs inside an `index()` loop. The Current
Workspace Resolver avoids this architecturally rather than papering
over it with `->with('workspace.users')`: `index()` actions never
authorize per-row at all — membership in the resolved workspace is
already guaranteed by `ResolveCurrentWorkspace` before the controller
runs, so listing is a plain `WHERE workspace_id = ?`, not N per-row
Gate checks. Single-resource actions (`show`/`update`/`destroy`) still
call `$this->authorize()` once per request, exactly as before — that
was never the N+1 risk; the list endpoints were.

## Chosen Solution

### Current Workspace Resolver

```
Current User → CurrentWorkspaceResolver → ResolveCurrentWorkspace (middleware) → CurrentWorkspaceContext → Controllers/Services
```

- **`App\Services\CurrentWorkspaceResolver`** — the *strategy*, isolated
  in one class. Reads an explicit `X-Workspace-Id` header or
  `workspace_id` query parameter if present, membership-checks it
  (`Workspace::hasMember()`, already written and tested in Milestone 7),
  and throws `AuthorizationException` if the workspace doesn't exist or
  the user isn't a member — deliberately the same failure for both
  cases (403, not 404), so a client can't distinguish "wrong workspace"
  from "workspace doesn't exist" by probing IDs. Falls back to
  `User::defaultWorkspace()` (the earliest-joined workspace by pivot
  `created_at`) when nothing is explicitly requested.
- **`App\Http\Middleware\ResolveCurrentWorkspace`** — runs after
  `auth:sanctum` on every workspace-scoped route group
  (`routes/api_v1.php`), calls the resolver once, and populates the
  context. Not applied to `/login`, `/logout`, `/user` — none of those
  operate on a workspace.
- **`App\Support\CurrentWorkspaceContext`** — a plain, request-scoped
  holder (`?Workspace $workspace`), bound `scoped()` (not `singleton()`)
  in `AppServiceProvider` — one instance per request, safely reset even
  under a long-lived worker (Octane) if this project ever runs one,
  though it doesn't today. Controllers/services depend on it via
  constructor injection (`SiteController`, `PostController`,
  `DashboardController`) instead of re-deriving or re-trusting a
  `workspace_id` themselves.

**Why this is the extensible shape the brief asked for.** A future
workspace switcher is a frontend change only (send a different
`X-Workspace-Id`) — no backend change. Subdomain-based tenancy
(`acme.wpstudio.com`) or a different resolution convention entirely is
a change to `CurrentWorkspaceResolver::resolve()` alone; every
controller/service that depends on `CurrentWorkspaceContext` is
unaffected. This is the concrete mechanism behind "future workspace
switching without rewriting every controller."

### Backend

- `composer require laravel/sanctum`; `$middleware->statefulApi()` in
  `bootstrap/app.php` (Laravel 12's built-in helper — prepends
  `EnsureFrontendRequestsAreStateful` to the `api` group).
  `config/sanctum.php`'s `stateful` list is env-driven
  (`SANCTUM_STATEFUL_DOMAINS`), matching `config/cors.php`'s existing
  `FRONTEND_URLS` pattern.
- `Http/Controllers/Api/V1/Auth/AuthController` (`login`, `logout`) and
  `UserController` (`show`, the profile endpoint) — a separate `Auth/`
  subnamespace, matching the project's one-controller-per-domain
  convention. `login()` regenerates the session ID on success (session-
  fixation mitigation — a pre-login session ID must never remain valid
  post-login); `logout()` invalidates the session and rotates the CSRF
  token.
- `App\Exceptions\InvalidCredentialsException` — a distinct 401 from
  plain `AuthenticationException` (`INVALID_CREDENTIALS` vs.
  `UNAUTHENTICATED`), so the frontend can tell "this login attempt was
  wrong" from "you got logged out" and show the right copy for each,
  through the same `ApiException`/`ApiExceptionHandler` machinery
  Milestone 6 built.
- `RateLimiter::for('login', ...)` in `AppServiceProvider` — 5
  attempts/minute keyed by `email|ip` together (see Security below).
- Routes (`routes/api_v1.php`): `/health` and `POST /login` public;
  everything else behind `auth:sanctum`; `sites`/`posts`/`dashboard/summary`/
  the placeholder domains additionally behind `ResolveCurrentWorkspace`.
  `/user` deliberately sits outside the workspace-resolving group — a
  user with zero workspace memberships must still see their own
  profile (`current_workspace_id: null`), not get a hard failure.
- `SiteController`/`PostController` now call `$this->authorize()` using
  the Milestone 7 Policies, unchanged — this milestone wires them in,
  it doesn't rewrite the authorization logic itself.

### Frontend

- `src/lib/api-client.ts` — `credentials: "include"` on every request;
  a `ensureCsrfCookie()` handshake before any mutating call
  (deduplicated against concurrent callers); reads `XSRF-TOKEN` and
  sends `X-XSRF-TOKEN` automatically. A `UNAUTHORIZED_EVENT` fires only
  on a genuine `UNAUTHENTICATED` 401 (not `INVALID_CREDENTIALS`, which
  would misfire on the login page itself) — one browser event, one
  listener (`useUnauthorizedListener`, wired once in `ProtectedLayout`),
  rather than every hook checking for 401s itself.
- `src/features/authentication/` — `services/auth.service.ts`
  (`login`/`logout`/`getCurrentUser`), `hooks/use-auth.ts`
  (`useCurrentUser`, `useLogin`, `useLogout`, `useUnauthorizedListener`),
  `components/login-form.tsx` (React Hook Form + Zod, the established
  stack). A 401 from `getCurrentUser()` is caught inside `queryFn` and
  resolved to `null`, not left to throw — "nobody is logged in" is a
  normal, expected result, not a query error to retry (`retry: false`).
- `ProtectedLayout` (`src/components/layout/protected-layout.tsx`) — a
  real client-side check against `useCurrentUser()`: a loading state
  while the check is in flight, a redirect to `/login?redirect=<path>`
  on `null` (the intended destination preserved unless it's `/dashboard`
  itself, the default post-login landing page), and the actual protected
  content otherwise.
- `src/app/(auth)/login/` — a new route group, its own minimal layout
  (no sidebar/header chrome), mirroring how `(app)` is its own group
  per `docs/adr/0002-product-shell.md`.
- `AppHeader`'s user menu (previously disabled placeholders) — "Sign
  out" now calls the real logout mutation; the avatar/label show the
  real signed-in user's initial and email.

## Security Considerations

- **CSRF.** Sanctum's double-submit cookie pattern: `GET /sanctum/csrf-cookie`
  sets a `XSRF-TOKEN` cookie (JS-readable, unlike the `httpOnly` session
  cookie); the frontend echoes it back as `X-XSRF-TOKEN` on every
  mutating request; Laravel verifies the two match. Centralized in
  `api-client.ts` — no call site remembers to do this itself.
- **Session fixation.** `login()` calls `$request->session()->regenerate()`
  — a session ID that existed before authentication is never valid
  after it.
- **Rate limiting.** 5 attempts/minute, keyed by `email` **and** IP
  together (`Str::lower($email).'|'.$request->ip()`), matching Laravel
  Fortify's own default threshold. Keyed by the pair, not IP alone or
  email alone: an attacker can't lock a real user out of their own
  account by spamming failed attempts against their email from a
  different IP each time, and one IP can't be blocked from ever
  attempting login against multiple legitimate accounts.
- **Credential-probing resistance.** A wrong password and a nonexistent
  email return the identical status (401) and error code
  (`INVALID_CREDENTIALS`) — a client can't use response shape to
  enumerate registered emails.
- **Workspace-probing resistance.** Same principle, applied to
  `CurrentWorkspaceResolver`: a nonexistent workspace ID and one the
  user genuinely isn't a member of both 403 identically.
- **Mass assignment / validation.** Unchanged posture from Milestones
  6–7 — `LoginRequest` validates shape before any authentication
  attempt; every model still uses explicit `$fillable`.

## Trade-offs

- **Sanctum SPA cookie auth requires a shared registrable domain (or
  configured subdomains) in production.** Local dev (`localhost:3000`
  / `:8000`) works via `SANCTUM_STATEFUL_DOMAINS` out of the box. The
  documented production target (`docs/PROJECT.md`'s Stack table:
  Vercel + Railway) is two unrelated domains unless custom domains
  under one root domain are set up before launch. Deliberately **not**
  solved here — named as Milestone 19 (Cloud Deployment & Security
  Hardening)'s job, the same pattern as the SQLite→MySQL production
  decision deferred since Milestone 6.
- **No Next.js edge `middleware.ts`.** The auth boundary is a
  client-side query check in `ProtectedLayout`, not an edge redirect.
  Accepted: Next.js can't meaningfully introspect Laravel's `httpOnly`
  session cookie without an API round-trip anyway, so an edge
  middleware would just be a different place to make the same request
  — more moving parts for the same actual guarantee.
- **Registration, workspace switching UI, email verification, password
  reset, 2FA, and social auth are all out of scope.** Each is named
  here deliberately (see Future IAM Roadmap), not silently dropped.
  `MustVerifyEmail` stays commented out on `User`; the
  `password_reset_tokens` table exists (Laravel's default scaffolding,
  Milestone 1) but nothing uses it yet.
- **`/api/v1/user` sits outside workspace resolution by design** — a
  small inconsistency (one endpoint doesn't go through the same
  middleware pipeline as the rest) accepted because the alternative
  (a workspace-less user gets a hard 403 on their own profile) is
  worse.

## Future IAM Roadmap

- **Registration + onboarding** (a future milestone): real
  self-registration, plus the actual product decision `0005` deferred
  and this ADR re-deferred — does registering create a new workspace,
  join an existing one via invite, or both? `CurrentWorkspaceResolver`
  and `User::defaultWorkspace()` don't need to change either way; a new
  user simply has zero/one workspace memberships until onboarding
  creates one.
- **Invitations** — a `workspace_invitations` table + accept flow,
  attaching an existing or new `User` to a `Workspace` via
  `workspace_user` (unchanged schema, already supports it).
- **Workspace switching UI** — the backend contract already supports
  this (`X-Workspace-Id`, `GET /api/v1/user`'s `workspaces` array);
  purely a frontend affordance (a switcher component + persisting the
  chosen ID) when it's actually needed.
- **Audit logs** — `AssignRequestId`'s request-ID correlation
  (Milestone 6) and `SiteConnected`/`LogSiteConnected`'s domain-event
  pattern (Milestone 7) are the two seams a real audit log would attach
  to; nothing new needed to start logging authentication/authorization
  events beyond wiring listeners.
- **Email verification** — `MustVerifyEmail` uncomment + a
  `verified` middleware group; scaffolding already present, unused.
- **Password reset** — `password_reset_tokens` table already exists;
  needs the actual request/reset controller pair and email delivery
  (currently `MAIL_MAILER=log` in dev).
- **2FA / social auth** — genuinely new scope; no existing scaffolding
  to build on, unlike the items above.

## Observability

No new logging/tracing infrastructure this milestone —
`AssignRequestId`/`SecureHeaders` and `ApiExceptionHandler`'s single
choke point (Milestone 6) already cover every new route automatically,
verified via the same `X-Request-Id` header check pattern used since
Milestone 6.

## Performance

Covered under "Eliminating the policy N+1" above — the Current
Workspace Resolver removes the risk architecturally for list endpoints;
no additional caching or query changes needed at today's scale.
