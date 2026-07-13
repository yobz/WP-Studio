# 0003 — Dashboard Data Architecture

**Status:** Accepted (Milestone 5)

## Decision

Back every dashboard widget with a Promise-returning mock service layer
(`src/services/mock/`) shaped like a future REST response, fetch it
through TanStack Query rather than component-local `useEffect`/`fetch`,
and keep global state (Zustand) to the one value that's genuinely
cross-cutting — everything else is local `useState` or server props.

## Context

**What is Milestone 5?** The first milestone with real (if mocked)
data and real async states — Loading/Empty/Error/Success — on top of
the Product Shell built in Milestone 4 (see
[[0002-product-shell]](0002-product-shell.md)). No Laravel API,
database, auth, or AI integration exists yet; the goal is a dashboard
that *behaves* like it's talking to a real backend so that replacing
the mock layer later is a service-file swap, not a component rewrite.

**Why does the fetching strategy matter now?** Every future module
(Content, WordPress, Analytics, AI) will need the same shape of
answer — how do we fetch, cache, retry, and show loading/error states —
so Milestone 5 is where that pattern gets decided once rather than
reinvented per widget.

## Alternatives Considered

**Data source — hardcoded JSX vs. a mock service layer.** Inlining
arrays directly in components is faster to write but means every
widget's "loading" and "error" states are fictional (nothing ever
fails or takes time), and swapping in Laravel later means rewriting
every component, not just a data file. Chose a dedicated
`src/services/mock/dashboard.service.ts`: every function returns a
`Promise` with a simulated network delay
(`delay()` in that file), so calling code already exercises real async
handling. `src/features/dashboard/types/dashboard.types.ts` is
deliberately plain data (no icon or component references) so those
shapes can be satisfied by an actual API response without changing the
type file; icon-per-id mapping lives in each component instead (e.g.
`KPI_ICONS` in `kpi-cards.tsx`).

**Fetching — component-local `useEffect` vs. TanStack Query.**
Hand-rolled `useEffect` + `useState` fetching is fewer moving parts for
a single call, but every widget would re-implement retry, staleness,
and cache invalidation slightly differently, and none of it would
carry over to the real API. Chose TanStack Query
(`src/features/dashboard/hooks/`, one thin `useQuery` wrapper hook per
widget, query keys namespaced under `["dashboard", ...]`) specifically
because the milestone needs to *demonstrate* caching and retry, not
just fetch data — `staleTime: 60_000` and `retry: 2` (exponential
backoff) are set once in `QueryProvider`
(`src/components/common/query-provider.tsx`) and inherited by every
hook, rather than repeated per call site.

**Demonstrating the Error state — random flakiness vs. deterministic
failure.** A `Math.random()` chance-of-failure is realistic but makes
the Error UI something you might never actually see in a demo (or
always see, if seeded wrong). Chose a deterministic failure instead:
`getRecentDrafts()` in `dashboard.service.ts` fails the first two calls
per browser session (module-scoped counter), then succeeds. Recent
Drafts is the one query with a `retry: 1` override (global default is
2) — so both automatic attempts are exhausted and the Error UI
actually renders before a manual "Try again" (the 3rd attempt)
succeeds. Verified end-to-end with Playwright: error state visible on
load, draft list visible after clicking "Try again."

**Global state — Zustand for notification count only, not dashboard
filters.** The milestone brief's own example list included "Dashboard
Filters" as a candidate for shared state. Reconsidered during
implementation: none of the five KPIs (Connected Sites, Published
Posts, Draft Posts, Monthly Visitors, Storage Usage) are
time-range-filterable in a way another widget needs to react to — only
Analytics Preview has a time dimension, and nothing else consumes it.
A shared filter store would be state nothing else reads. Analytics
Preview's `range` is local `useState` in `analytics-preview.tsx`
instead. The one Zustand store this milestone adds
(`src/store/notification-store.ts`) is genuinely cross-cutting: the
header's notification badge and (eventually) whatever writes to it
live in unrelated component trees, which is the actual justification
for reaching for global state rather than prop drilling.

**Notification "read" state — clear-on-open vs. explicit action.**
Considered clearing the count automatically when the header's
`Popover` opens. Rejected before implementing: the clear would trigger
a re-render that removes the "you have N updates" message before the
user has time to read it — a real UX bug, not just a style preference.
`AppHeader` instead shows an explicit "Mark all as read" button that
calls `clearNotifications()`.

**Server vs. Client Components — accepted tension with TanStack
Query.** The project's general preference (`docs/adr/0002-product-shell.md`,
Milestone 4's own review notes) is Server Components by default,
Client Components only where interactivity requires it. `useQuery` is
inherently a client hook, so every widget that fetches through it
(all except `QuickActions`, which is static) is a Client Component.
This is a deliberate trade-off, not an oversight: a real SaaS
dashboard's widgets need to revalidate, retry, and eventually poll
independently of full-page navigation, which is exactly what
client-side query state buys. The page itself
(`src/app/(app)/dashboard/page.tsx`) stays a Server Component that
composes Client Component children — the same pattern already
established for `DashboardLayout` composing `AppSidebar`/`AppHeader`.

**Greeting/date — server-rendered vs. mount-time client render.**
`WelcomeSection` needs "Good morning" and today's date, both functions
of the visitor's clock. This route has no per-user data yet, so
Next.js statically generates it — a value computed from `Date.now()`
at build time would go stale for anyone visiting after that build.
Rather than force the whole dashboard route to render dynamically per
request (losing static generation for a page that's otherwise
identical for everyone), `WelcomeSection` computes the greeting/date
after mount (the same `mounted` guard already used by `ThemeToggle`
for the equivalent SSR-vs-client-state problem), rendering a `Skeleton`
until then. Confined the client-time dependency to one small
component instead of the whole page.

## Chosen Solution

- `src/services/mock/dashboard.mock-data.ts` — fixture data;
  `dashboard.service.ts` — `delay()`-wrapped async functions standing
  in for REST calls.
- `src/features/dashboard/types/dashboard.types.ts` — plain data
  shapes, no presentation concerns.
- `src/features/dashboard/hooks/use-*.ts` — one `useQuery` wrapper per
  widget; `use-recent-drafts.ts` is the one with a `retry` override.
- `src/components/common/query-provider.tsx` — `QueryClient` (created
  in `useState`, not module scope, so SSR requests don't share a
  client) with the shared `staleTime`/`retry`/`retryDelay` defaults;
  dev-only `ReactQueryDevtools`, dynamically imported with `ssr: false`
  so it never reaches the production bundle or the server render.
- `src/store/notification-store.ts` — the one Zustand store this
  milestone adds.
- `src/features/dashboard/utils/status-meta.ts` — shared
  status-to-badge-color mapping (`SiteStatus`, `ServiceStatus`),
  extracted after the same table appeared identically in both
  `wordpress-overview.tsx` and `system-health.tsx`.
- `src/features/dashboard/components/*.tsx` — nine widgets, each
  composing existing `common/`/`ui/` primitives (`StatCard`,
  `StatusBadge`, `EmptyState`, `LoadingState`, `Card`, `Skeleton`,
  `Progress`) rather than introducing new ones, plus one new common
  component (`LoadingState`) added this milestone because no existing
  primitive covered a centered spinner-plus-message loading placeholder.

## Trade-offs

- Nearly every dashboard widget is a Client Component — accepted per
  the "Server vs. Client Components" reasoning above; the alternative
  (fetch server-side, no client revalidation) doesn't scale to a real
  SaaS dashboard that needs retry/refetch without a full page reload.
- The deterministic Recent Drafts failure is module-counter state, not
  per-request — it resets on a full page reload but not on client-side
  navigation within the app. Fine for demonstrating the pattern once;
  a real backend's failures won't be this convenient (or this legible)
  to demo.
- `status-meta.ts` was extracted mid-milestone rather than anticipated
  up front — two call sites with identical logic was the trigger, not
  a guess at future reuse. Consistent with the project's stated
  preference for reacting to real duplication over pre-abstracting.

## Future Implications

- Replacing mocks with Laravel: swap the function bodies in
  `dashboard.service.ts` for real `fetch()` calls. No hook, component,
  or type should need to change, since the return types already match
  the shape a REST response would take.
- `QueryProvider`'s `staleTime`/`retry` defaults are dashboard-scoped
  today only because the dashboard is the only feature using them —
  they're set at the app-wide `QueryClientProvider`, so future modules
  (Content, Analytics) inherit the same caching/retry behavior for
  free without reconfiguring anything.
- `useNotificationStore` is intentionally the *only* Zustand store —
  the next module that reaches for global state should first ask
  whether local state or a query cache already covers it, per the
  reasoning above, rather than defaulting to a new store.
- AI Assistant Preview's future integration point is documented inline
  in `ai-assistant-preview.tsx` (a `POST /api/ai/drafts`-shaped call
  where `Generate` currently does nothing) rather than only in this
  ADR, so the next milestone that implements it finds the note at the
  point of use.
