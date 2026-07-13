# 0002 — Product Shell

**Status:** Accepted (Milestone 4)

## Decision

Build the application shell (sidebar, header, layout, routing) on top
of shadcn's own `sidebar` primitive rather than hand-rolling collapse/
responsive/keyboard behavior; drive navigation from a single
configuration array; scope the shell to a Next.js route group so
future non-shell routes (auth pages, etc.) aren't forced into it; and
wire a placeholder auth boundary now rather than retrofitting one
later.

## Context

**Who is the user?** Someone managing one or more WordPress sites who
needs a persistent, low-friction way to move between site management,
content, analytics, and settings — a standard SaaS operator, not an
end reader of the sites themselves.

**First experience?** Landing in the shell (currently `/`, an
"Overview" placeholder) with the sidebar and header already in place —
navigation should never feel like it's still being built, even though
every page's content is a placeholder right now.

**Always-accessible actions?** Sidebar navigation (collapsible but
never fully hidden on desktop), search, notifications, and the user
menu — all live in the persistent header/sidebar chrome, not buried in
page content.

**Scaling to Laravel/WordPress/Analytics/AI modules?** Every future
module should cost one navigation config entry and one route folder —
nothing structural in `AppSidebar`, `AppHeader`, or the layout
components should need to change.

## Alternatives Considered

**Sidebar — hand-rolled vs. shadcn's `sidebar` primitive.** Hand-rolling
collapse state, the desktop/mobile breakpoint switch, keyboard
shortcuts, and focus handling from scratch would be more tailored but
re-implements something the registry already solves correctly — and
Milestone 3.1's lessons (real, subtle a11y bugs are easy to introduce
and easy to miss without measurement) argued strongly against
reinventing this by hand. Chose the primitive. This surfaced a second
decision: `npx shadcn add sidebar` reported it would **overwrite**
`button.tsx`, `input.tsx`, `skeleton.tsx`, and `tooltip.tsx` — files
already hardened with Milestone 3.1's accessibility enforcement.
Inspected `sidebar.tsx`'s actual source first (`--view`) and confirmed
it only does standard `import { Button } from "@/components/ui/button"`
— it needs the *exports* to exist with compatible props, not the
CLI's exact vendor bytes. Extracted the four genuinely new files
(`sidebar.tsx`, `sheet.tsx`, `separator.tsx`, `use-mobile.ts`) by hand
instead of running `add` directly, leaving the hardened files
untouched. This is now the standing project rule (see ADR 0001).

**Navigation — hardcoded JSX vs. configuration array.** Hardcoding nav
items directly in `AppSidebar`'s JSX is simpler for a fixed set of
routes but fails the milestone's own scaling requirement — every future
module would mean editing the sidebar component itself. Chose a single
`src/lib/navigation.ts` array (`{ title, href, icon }`, grouped) that
both `AppSidebar` and the header's breadcrumb resolver read from — one
source of truth, zero sidebar edits for future modules.

**Route structure — flat routes vs. a route group.** Placing
`/dashboard`, `/content`, etc. as flat top-level routes would work today,
but Milestone 8 (Authentication) will need routes that *don't* get the
dashboard shell (a login page shouldn't have a sidebar). Chose a
`(app)` route group: transparent to the URL (`/dashboard`, not
`/app/dashboard`), scopes the shell `layout.tsx` to exactly these
routes, and leaves room for a sibling `(auth)` group later without
restructuring anything that exists today.

**Auth boundary — build now vs. defer to Milestone 8.** Deferring is
simpler today, but means retrofitting a session check into every
existing route later. Chose to wire a `ProtectedLayout` placeholder
(currently a pass-through) into the route group now — Milestone 8 only
has to implement the check inside a component that already wraps every
shell route, not go find and wrap each route individually.

**Theme toggle — functional vs. UI-only placeholder.** The header
requirements list marks "Notification button" and "User Menu"
explicitly "(placeholder)" but "Theme Toggle" carries no such
qualifier — read as an intentional signal that, unlike notifications/
user data (which need a real backend), theme switching needs no
backend at all and could be fully real without violating "no business
functionality yet." Chose `next-themes` (rather than hand-rolling
`localStorage` + a `useEffect`) specifically to avoid a flash-of-
wrong-theme on load — a real Next.js SSR/hydration problem the library
solves correctly and hand-rolling risks getting subtly wrong.

## Chosen Solution

- `src/components/ui/{sidebar,sheet,separator}.tsx` +
  `src/hooks/use-mobile.ts` — hand-extracted from the registry, not
  `add`-installed, to protect Milestone 3.1's `Button` hardening.
- `src/lib/navigation.ts` — the single navigation config; `AppSidebar`
  maps over it, the header's `getNavTitle()` reads from it for
  breadcrumbs.
- `src/components/layout/{app-sidebar,app-header,dashboard-layout,
  protected-layout}.tsx` — composition layer; `DashboardLayout` and
  `ProtectedLayout` are Server Components composing Client Component
  children (`AppSidebar`/`AppHeader` need `usePathname`/interactivity),
  keeping the client bundle to only what actually needs it.
- `src/app/(app)/` — route group holding `layout.tsx` (the shell),
  `loading.tsx` (skeleton, scoped so only the content region re-renders
  during navigation, not the whole shell), `error.tsx`, and the six
  placeholder pages.
- `src/app/not-found.tsx` — deliberately **outside** the route group;
  a 404 shouldn't assume the dashboard shell is relevant for a
  completely unmatched URL.

## Trade-offs

- The sidebar primitive is ~700 lines — large for a single file, but
  it's vendor-integrated infrastructure implementing a genuinely hard,
  well-specified contract (collapse, responsive, keyboard, focus);
  same exemption already established for `Card`/`Tooltip` in Milestone
  3. Splitting it would fight the CLI's own file boundary for no
  benefit.
- `next-themes` is a new runtime dependency for what's a small amount
  of logic — accepted given the real SSR-flash correctness risk of
  hand-rolling it, and its near-zero size/dependency footprint.
- `AppSidebar`/`AppHeader` are Client Components (need `usePathname`,
  interactivity) — the necessary, not gratuitous, use of the client
  boundary; every page they wrap stays a Server Component.

## Future Implications

- Adding a module (e.g. a Laravel-backed feature) means: one entry in
  `navigation.ts`, one folder under `src/app/(app)/`. No sidebar/header
  edits required — this was verified as true by construction, not just
  asserted.
- `ProtectedLayout` is the one file Milestone 8 needs to implement a
  real check inside; every shell route already sits behind it.
- A future `(auth)` route group (login, etc.) can sit alongside `(app)`
  without a real auth check yet — `src/app/not-found.tsx` and the root
  `layout.tsx` already don't assume the shell exists.
- `layout/` components intentionally don't carry `data-slot` attributes
  the way `ui/`/`common/` primitives do — that convention exists to
  give *reusable, multi-consumer* components a stable styling API
  surface; `AppSidebar`/`AppHeader`/`DashboardLayout` have exactly one
  consumer (the app itself), so there's no external API to stabilize.

## Update — Milestone 4.1 (Product Shell Hardening)

**Nested `<main>` fix.** The Milestone 4 report found `DashboardLayout`
rendering its own `<main>` inside `SidebarInset`, which is itself
`<main>`. Fixed by changing `DashboardLayout`'s inner wrapper to a
`<div>` — `SidebarInset` was already the correct semantic landmark, so
this is a bug fix within the existing decision, not a change to it.

**Mobile search — decision this milestone had to make.** The header
requirements always wanted search on mobile; Milestone 4 shipped it
`hidden` below `sm` instead of solving the responsive case. Considered
the three alternatives the milestone brief offered — `Sheet`, `Dialog`,
`Popover` — against a plain inline-expand pattern (search icon toggles
the header row to a full-width `SearchInput` + close button). Chose
inline-expand: `Sheet`/`Dialog` are built for content that needs a
dedicated screen/backdrop, which is more machinery than revealing one
input needs (and we'd be repurposing the `Sheet` already pulled in for
the sidebar's mobile drawer, not reaching for the simplest available
tool); `Popover` is anchored/floating by design, awkward for something
that wants full header width on a narrow screen. Inline-expand needs
zero new primitives or dependencies — just one `useState<boolean>` in
`AppHeader` — and matches the pattern most mobile web/native search
affordances already use (e.g. iOS Safari's address bar search).

**`EmptyState`'s heading level.** Not anticipated in the original
Milestone 4 decision at all — surfaced during this milestone's
verification (see `docs/ENGINEERING_JOURNAL.md`). `EmptyState`
hardcoded an `<h3>`, which was correct nowhere: it skips a level after
`PageHeader`'s `<h1>` on every placeholder page, and leaves pages with
no `<h1>` at all where `EmptyState` is the only heading (404, the error
boundary). Rather than pick one fixed level, added an optional
`titleAs` prop (`"h1" | "h2" | "h3"`, default `"h2"` — correct for the
common case of following a page's `PageHeader`) so the two exceptions
(`not-found.tsx`, `(app)/error.tsx`) can opt into `"h1"` explicitly.
Kept as a prop rather than inferring it automatically (e.g. from
whether a `PageHeader` is an ancestor) — that would require context or
DOM inspection for something four call sites can just state directly;
simpler, and correct is more valuable than clever here.