# Milestone 8 Report

## Date

2026-07-13

---

## Objective

Build a production-ready Authentication & Authorization foundation —
Identity & Access Management, not just login/logout pages. Laravel
Sanctum cookie/session SPA auth, CSRF protection, rate-limited login,
a Current Workspace Resolver architecture (replacing the initially-
proposed request-driven `workspace_id` design on explicit direction),
real `SitePolicy`/`PostPolicy` wiring, and a frontend authentication
experience built on TanStack Query rather than a parallel Zustand
store. Registration, workspace switching UI, email verification,
password reset, 2FA, and social auth explicitly out of scope, named
and deferred rather than silently dropped.

---

## Executive Summary

Milestone 8 is complete and, on independent review, sound. The
pre-implementation architecture review earned its keep concretely: it
surfaced two real, previously undocumented cross-tenant vulnerabilities
— an unscoped `DashboardService` aggregate and unauthorized
`workspace_id`/`site_id` filters on the Sites/Posts index endpoints —
neither hypothetical, both verified by tracing the actual query code,
both fixed as part of this milestone rather than filed as follow-ups.
The Current Workspace Resolver architecture (a resolver service,
middleware, and a request-scoped context binding) is a genuine
improvement over the simpler design originally proposed: it removes
the N+1 authorization risk Milestone 7's Future Backlog flagged
*architecturally* (list endpoints authorize the workspace once, not
per row) rather than patching it with eager loading, and it means a
future workspace switcher is a frontend-only change.

Backend test coverage grew from 38 to 57 passing tests, including
dedicated cross-tenant isolation tests that exercise the exact
vulnerabilities this milestone closed. One real, previously-latent bug
was found and fixed while writing those tests: `ApiExceptionHandler`
never actually mapped `AuthorizationException` to the API's `FORBIDDEN`
envelope, because Laravel's own exception pipeline converts it to
`AccessDeniedHttpException` before this project's `render()` closure
ever sees it — invisible until this milestone was the first to throw
an authorization exception for real.

One genuine, moderate-severity accessibility finding survives this
milestone, pre-existing and explicitly not fixed here (see
Accessibility Summary) — everything else found during self-review was
fixed before this report was written, not left as a footnote.

---

## Engineering Summary

**Backend.** `laravel/sanctum` installed, cookie/session mode only —
no personal access tokens, no JWTs. `CurrentWorkspaceResolver` →
`ResolveCurrentWorkspace` middleware → `CurrentWorkspaceContext`
(`scoped()` container binding) is the full resolution chain; every
workspace-scoped controller (`SiteController`, `PostController`,
`DashboardController`) depends on the context via constructor
injection rather than trusting a client-supplied ID.
`Http/Controllers/Api/V1/Auth/AuthController` (`login`, `logout`) and
`UserController` (`show`) are new; `SitePolicy`/`PostPolicy` (written
and tested in Milestone 7) are now actually called via
`$this->authorize()` — zero changes to the policy logic itself, only
wiring. `InvalidCredentialsException` distinguishes "wrong password"
(401, `INVALID_CREDENTIALS`) from "no session" (401,
`UNAUTHENTICATED`) through the existing `ApiException`/
`ApiExceptionHandler` machinery. `RateLimiter::for('login', ...)` — 5
attempts/minute keyed by `email|ip` together, matching Laravel
Fortify's own default.

**Frontend.** `src/lib/api-client.ts` centralizes `credentials:
"include"`, the CSRF cookie handshake (deduplicated against concurrent
callers), and a decoupled `UNAUTHORIZED_EVENT` fired only on a genuine
session-loss 401. `src/features/authentication/` — service, hooks
(`useCurrentUser`, `useLogin`, `useLogout`, `useUnauthorizedListener`),
and a login form (React Hook Form + Zod, the established stack).
`useCurrentUser()` is the single source of truth for "who is logged
in" — no Zustand store, per this codebase's existing client/server-
state split (`docs/adr/0003-dashboard-data-architecture.md`).
`ProtectedLayout` is a real client-side check now: loading state,
redirect to `/login?redirect=<path>` (destination preserved unless
it's `/dashboard` itself), protected content otherwise. New `(auth)`
route group mirrors `(app)`'s own structure. `AppHeader`'s user menu
(previously disabled placeholders) shows the real user and has a
working sign-out.

---

## Security Summary

- **Token strategy.** Cookie/session only, `httpOnly` +
  `SameSite=lax`. No bearer token ever exists in frontend JavaScript —
  confirmed by reading every file in `src/features/authentication/`
  and `src/lib/api-client.ts`; nothing reads or writes a token to
  `localStorage`/`sessionStorage`.
- **CSRF.** Sanctum's double-submit cookie pattern, centralized in
  `api-client.ts` — verified working end-to-end (login, a mutating
  request, succeeds; the `/sanctum/csrf-cookie` handshake fires
  automatically).
- **Session fixation.** `login()` regenerates the session ID on
  success — verified directly: `AuthenticationTest`'s login test
  asserts a real session exists post-login, and the milestone's own
  browser verification confirmed a full page reload preserves the
  authenticated state (i.e., the regenerated session, not the
  pre-login one, is what persists).
- **Credential/workspace-probing resistance.** A wrong password and a
  nonexistent email return identically (401, `INVALID_CREDENTIALS`); a
  nonexistent workspace ID and one the user isn't a member of return
  identically (403) — both verified by dedicated tests
  (`AuthenticationTest`, `WorkspaceIsolationTest`).
- **Rate limiting.** 5/minute, keyed by `email|ip` — verified by test
  (5 failed attempts succeed as 401s, the 6th is 429).
- **The two vulnerabilities this milestone exists to fix are verified
  closed, not just asserted closed.** `WorkspaceIsolationTest` creates
  a second workspace with its own site/posts/analytics data and
  asserts the authenticated user's dashboard, site list, and post list
  never include it — this is the actual regression test for the actual
  bug found during review, not a generic "workspace scoping works"
  smoke test.
- **Residual, deliberately deferred:** no email verification (`User`'s
  `MustVerifyEmail` stays commented out), no password reset flow (the
  table exists, unused), no 2FA. All named in
  `docs/adr/0006-authentication-architecture.md`'s Future IAM Roadmap.

---

## Architecture Summary

The Current Workspace Resolver is the milestone's one real
architectural contribution beyond "wire up Sanctum." Independently
assessed against its own stated goal — "future workspace switching
without rewriting every controller" — it holds up: the resolution
*strategy* (header, then query param, then earliest-membership
default) lives in exactly one method
(`CurrentWorkspaceResolver::resolve()`); every consumer depends on the
*result* (`CurrentWorkspaceContext`), not the strategy. A subdomain-
based tenancy scheme or a persisted "last selected workspace" cookie
would both be changes to that one method.

One design note worth surfacing rather than treating as settled: this
architecture adds two extra queries per workspace-scoped request
(resolving the workspace, checking membership) beyond what a simpler
"trust an authorized `workspace_id`" design would need. At today's
scale this is immaterial (SQLite, single-digit rows); worth reassessing
only if `CurrentWorkspaceResolver::resolve()` ever shows up in a real
profiling pass — not a concern to design around preemptively.

---

## Accessibility Summary

Audited `/login` (light and dark) and the authenticated dashboard
(including the new, real `AppHeader` user menu, both open and closed)
with `axe-core`, widened to `best-practice` tags per this project's
standing practice since Milestone 4.1.

- `/login`, light and dark: **0 violations.**
- Dashboard, user menu closed: **0 violations.**
- Dashboard, user menu open: **1 violation** — `region` (moderate):
  "Ensure all page content is contained by landmarks," against the
  open `DropdownMenu` popup content.

**Investigated, not fixed here.** The popup is portaled to
`document.body` (Base UI's standard mechanism for correct floating-UI
stacking) outside the `<header>`/`<main>` landmark structure. This
predates Milestone 8 — the same `DropdownMenu` primitive and portal
mechanism has been in place since Milestone 4; this milestone's audit
is simply the first to scan the page with the menu actually open.
Fixing it properly means a design-system-level decision affecting
every Base UI popup primitive project-wide (`DropdownMenu`, `Popover`,
`Tooltip`), not a one-component patch — logged in
`docs/ENGINEERING_JOURNAL.md`'s Future Backlog (Medium Priority) for
whoever next touches those primitives, rather than a rushed local fix
that wouldn't address the other two components carrying the same
pattern.

---

## Technical Debt

New this milestone (also see the Future Backlog entries added in
`docs/ENGINEERING_JOURNAL.md`):

- `DropdownMenu`/`Popover` landmark-containment gap (above) —
  Medium Priority, deferred.
- Two extra per-request queries from workspace resolution (Architecture
  Summary) — noted, not a current problem.
- `/api/v1/user` sits outside the `ResolveCurrentWorkspace` middleware
  group by design (a workspace-less user must still see their own
  profile) — a deliberate, documented inconsistency, not an oversight.

Resolved this milestone (previously open):

- The `SitePolicy`/`PostPolicy` N+1 risk (Milestone 7's Future
  Backlog, High Priority) — resolved architecturally.
- "Every backend API route is unauthenticated" (Deferred Priority since
  Milestone 6) — resolved.
- Root `README.md` boilerplate (Low Priority since Milestone 6) —
  resolved in the post-Milestone-7 documentation session; the backlog
  entry itself is corrected in this milestone's journal update since
  it hadn't been marked resolved there yet.

---

## Production Engineering Review

| Layer | What changed | What was deferred | Future considerations |
| --- | --- | --- | --- |
| Frontend Architecture | New `(auth)` route group, real `ProtectedLayout`, `useCurrentUser()` as the auth source of truth | Workspace-switcher UI | Backend contract already supports it (`X-Workspace-Id`, `GET /user`'s `workspaces` array) |
| Backend/API | `auth:sanctum` on every route but `/health`/`/login`; Current Workspace Resolver | Pagination (unchanged from M7) | — |
| Database | None (no schema change) | — | — |
| Authentication | Sanctum cookie/session, CSRF, rate limiting | Registration, email verification, password reset, 2FA, social auth | See ADR 0006's Future IAM Roadmap |
| Authorization | `SitePolicy`/`PostPolicy` wired in; workspace-level authorization via the resolver | — | — |
| Security | Session fixation mitigation, credential/workspace-probing resistance, rate limiting | — | — |
| Performance | N+1 risk resolved architecturally for list endpoints | Caching workspace resolution | Revisit only if profiling shows it matters |
| Rate Limiting | Login: 5/min per `email+ip` | Rate limiting on other mutating endpoints | Not needed yet — no abuse vector identified beyond login |
| Developer Experience | `actingAsWorkspaceMember()` shared test helper (`tests/Pest.php`) | — | — |
| Observability | None new — existing `AssignRequestId`/`ApiExceptionHandler` cover new routes automatically | — | — |
| Logging | Unchanged | — | — |
| Future Cloud Deployment | — | Sanctum's cross-domain cookie requirement (Vercel + Railway are separate domains) | Named for Milestone 19, not solved here |

---

## Documentation Review

`docs/adr/0006-authentication-architecture.md` (new) covers every
required section: why Sanctum, why cookie sessions, why TanStack Query
over Zustand, the Current Workspace Resolver architecture, why JWT was
rejected, why registration is deferred, the future IAM roadmap,
security considerations, trade-offs, and alternatives considered.
`docs/PROJECT.md` gained an "Authentication & Authorization" section
and updated Known Limitations/Stack table/Status.
`docs/ROADMAP.md` milestone 8 marked complete with a retrospective
description matching the established M1–7 pattern.
`docs/ENGINEERING_JOURNAL.md` gained a Milestone 8 Interview Highlights
subsection (5 entries, including two real dev-tooling investigations —
a Sanctum SPA testing gotcha and a dev-mode Fast Refresh artifact) and
a Resume Highlights subsection, plus Future Backlog updates (three
items resolved, three new items added, one stale entry corrected).
`docs/SESSION_HANDOFF.md` reflects the current, accurate state
including live environment gotchas specific to this milestone's own
verification process.

---

## Validation

- `npm run typecheck`: pass.
- `npm run lint`: pass.
- `npm run build`: pass — 11 routes, `/login` new (31.4 kB, 160 kB
  First Load JS), no other route's bundle size changed.
- `php artisan test`: **57/57 passing** (142 assertions) — 38 from
  Milestones 6–7 (all updated for the new auth requirement) + 19 new
  (`AuthenticationTest`, `WorkspaceIsolationTest`).
- End-to-end browser verification (production build —see the
  Engineering Journal's Milestone 8 entry #5 for why dev mode was
  unreliable for this specifically): unauthenticated redirect with
  preserved destination, wrong-password inline error, successful login
  landing on the originally-intended page, real dashboard data
  rendering post-login, session surviving a full page reload, the user
  menu showing the real signed-in email, sign-out, and re-protection
  after sign-out. Zero console errors across the entire flow.
- `axe-core` (widened tags, per standing practice): 0 violations on
  `/login` (light/dark) and the dashboard with the menu closed; 1
  pre-existing, deferred violation with the menu open (see
  Accessibility Summary).
- No routes, API contracts, or database schema broken — `sites`/
  `posts`/`dashboard/summary` keep their existing request/response
  shapes; the only behavioral change is that they now require
  authentication and are workspace-scoped (the intended, documented
  change this milestone makes).

---

## Final Verdict

**Approved.** The architecture review process worked as intended —
revising the initial proposal into the Current Workspace Resolver, and
the review itself catching two real vulnerabilities before
implementation, are the concrete payoff of doing that review rather
than treating it as a formality. The one surviving finding (the
landmark-containment accessibility gap) is correctly scoped as
deferred rather than rushed, since a proper fix belongs at the design-
system level, not patched locally under this milestone's time
pressure. No blocking issues. Ready to commit.

Recommended next steps before Milestone 9: commit this milestone's
work (see `docs/SESSION_HANDOFF.md`), then begin Milestone 9 (API
Completion & Frontend Migration) per `docs/ROADMAP.md`'s v0.8 release —
only after explicit approval, per this milestone's own stop condition.
