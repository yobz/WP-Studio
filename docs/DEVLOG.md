# Devlog

## 2026-07-13 — Milestone 5: Dashboard Experience

First milestone with real (mocked) data and real async states. Nine
dashboard widgets on top of the existing Product Shell — no shell code
rewritten, every widget composes existing `common/`/`ui/` primitives.
Full reasoning in `docs/adr/0003-dashboard-data-architecture.md` and
`docs/ENGINEERING_JOURNAL.md`; this entry is the what.

**Data/state infrastructure (built first, before any widget UI)**
- `src/services/mock/dashboard.mock-data.ts` + `dashboard.service.ts`
  — fixture data and `delay()`-wrapped async functions standing in for
  a future Laravel REST API.
- `src/features/dashboard/types/dashboard.types.ts` — plain data
  shapes (`Kpi`, `ActivityItem`, `WordPressOverview`, `AnalyticsPoint`,
  `Draft`, `SystemHealth`, ...), no presentation concerns.
- `src/components/common/query-provider.tsx` — `QueryClient` (created
  per-instance via `useState`, not module scope), `staleTime: 60s`,
  `retry: 2` with exponential backoff, dev-only `ReactQueryDevtools`
  (dynamically imported, `ssr: false`). Wired into the root layout as
  the outermost provider.
- `src/features/dashboard/hooks/use-*.ts` — six `useQuery` wrapper
  hooks, one per widget's data need, query keys under `["dashboard",
  ...]`.
- `src/store/notification-store.ts` — the one Zustand store this
  milestone adds (notification count), wired into the header's
  existing notification `Popover` with an explicit "Mark all as read"
  button (considered clearing on popover-open, rejected — would clear
  the count before the user could read the "you have N updates"
  message).
- `src/components/common/loading-state.tsx` — one new shared
  component (centered spinner + message, `role="status"`); every other
  widget need was covered by existing primitives.
- `src/components/ui/progress.tsx` — added via `npx shadcn add
  progress --dry-run` first (confirmed pure create, zero conflicts),
  then the real run.

**Nine widgets** (`src/features/dashboard/components/`), composed on
the dashboard page (`src/app/(app)/dashboard/page.tsx`) in the brief's
specified order:
- **Welcome Section** — greeting/description/date/workspace-selector
  placeholder. Greeting and date are computed client-side after mount
  (reusing `ThemeToggle`'s existing `mounted`-guard pattern), not
  server-rendered — this route is statically generated, so a
  build-time `Date.now()` would go stale for later visitors. See
  `docs/ENGINEERING_JOURNAL.md`.
- **KPI Cards** — 5 metrics via `useKpis()`, composes the existing
  `StatCard` directly (no new component needed; `Kpi.trend`'s shape was
  designed to match `StatCard`'s prop exactly).
- **Quick Actions** — 4 static placeholder cards, genuinely `disabled`
  (not `aria-disabled` + `preventDefault()` — that two-layer pattern is
  for links with a real destination to guard against; these buttons
  have none). Only new component in this milestone that's a Server
  Component (no data fetching, no interactivity).
- **Recent Activity** — mock timeline via `useRecentActivity()`,
  relative timestamps via a new `formatRelativeTime()` util
  (`src/lib/format.ts`, `Intl.RelativeTimeFormat`).
- **WordPress Overview** — one mock site, `StatusBadge` for connection
  status.
- **Analytics Preview** — Recharts `AreaChart`, local `useState` range
  toggle (7D/30D/90D) — deliberately not Zustand; nothing else on the
  dashboard reacts to it. Chart colors reference the existing
  `--chart-1..5` CSS custom properties directly (already defined in
  `globals.css`, unused until now). Chart wrapped in `role="img"` with
  a computed `aria-label` summarizing the trend range for screen
  readers.
- **Recent Drafts** — the retry/error demonstration widget. The mock
  service fails the first two calls per browser session deterministically
  (module counter, not `Math.random()`), paired with a `retry: 1`
  override (global default is 2) so both automatic attempts are
  guaranteed to exhaust before the Error UI renders. Verified
  end-to-end with Playwright: error visible on load, draft list visible
  after clicking "Try again." See `docs/ENGINEERING_JOURNAL.md`.
- **AI Assistant Preview** — prompt textarea + suggested-prompt chips
  (both interactive — filling the textarea is real UX to preview) +
  `Generate` button, genuinely `disabled` (not silently inert on click)
  since there's no backend yet. Future integration point
  (`POST /api/ai/drafts`-shaped) documented inline.
- **System Health** — service status badges (API, background queue)
  and `Progress` for storage usage.

**Duplication caught during self-review.** `SiteStatus`/`ServiceStatus`
→ badge-color mapping was written identically in both
`wordpress-overview.tsx` and `system-health.tsx`. Extracted to
`src/features/dashboard/utils/status-meta.ts`, imported by both —
reactive extraction (two real call sites triggered it), not a
speculative shared-utils file built ahead of need.

**Verification** — Playwright across four viewports (375/768/1440/
1920px): zero console errors on every breakpoint. `axe-core` (WCAG 2A/
2AA/2.1AA + `best-practice` tags, the widened scope established in
Milestone 4.1): 0 violations. Interaction checks: Analytics range
toggle (`aria-pressed` updates correctly), AI Assistant suggested-prompt
click fills the textarea, notification popover opens/shows empty state,
Recent Drafts error→retry→success flow (separate script, both
assertions passed on the first run). `typecheck`, `lint`, `build` all
pass. Cleaned up all temporary verification scripts and uninstalled
`playwright`/`@axe-core/playwright` afterward, as established practice.

**Documentation** — `docs/adr/0003-dashboard-data-architecture.md`
(new); `docs/ENGINEERING_JOURNAL.md` gained two dated investigation
entries plus a new permanent "Future Backlog" section
(High/Medium/Low/Deferred, rolling up open items from this and prior
milestones); `docs/PROJECT.md` gained a "Dashboard Experience" section
and updated Known Limitations/Status; `docs/ROADMAP.md` milestone 5
marked complete with an updated description, milestone 6 annotated
noting its core infrastructure was already delivered here.

## 2026-07-11 — Milestone 4.1: Product Shell Hardening

Patch milestone resolving every confirmed issue in
`docs/MILESTONE_REPORT_M4.md` before Dashboard work begins. No new
product functionality — every change is a fix, a UX gap closed, or
cleanup. Full reasoning in `docs/adr/0002-product-shell.md`'s update
section and `docs/ENGINEERING_JOURNAL.md`; this entry is the what.

**1. Nested `<main>` landmark** — `DashboardLayout` rendered its own
`<main>` inside `SidebarInset`, which is itself `<main>`. Fixed by
changing the inner wrapper to a `<div>`. Also gave `not-found.tsx` its
own `<main>` — it sits outside the `(app)` route group, so it never had
one at all. Verified: every route now has exactly one `<main>` (checked
via `page.locator("main").count()` across dashboard, content, and the
404 page).

**2. Mobile search UX** — was `hidden` entirely below `sm`, not
degraded. Added an inline-expanding pattern: a search icon button
swaps the header's breadcrumb/icon row for a full-width `SearchInput`
+ close button (`useState<boolean>` in `AppHeader`, no new
dependencies or primitives). Chose this over `Sheet`/`Dialog`/`Popover`
— see the ADR update for the full reasoning. Verified: autofocus lands
on the input on open, closes cleanly, no console errors.

**3. Navigation architecture** — evaluated `isActive = pathname ===
item.href` per the brief's instruction. Left unchanged: no nested
route exists yet to design or test a better matching rule against, and
guessing ahead of a real need risks getting it wrong. Documented
inline (a comment at the call site) and in the ADR for whoever adds
the first nested route.

**4. Project cleanup** — removed two stray `.gitkeep` files:
`src/components/layout/.gitkeep` (already known from the M4 report)
and `src/hooks/.gitkeep` (a second instance the report's own review
missed — caught by systematically checking every `.gitkeep` in the
project against its directory's actual contents, not just the one
already flagged). All other `.gitkeep` files checked and confirmed
still legitimately empty (reserved for future milestones) — none
removed.

**Also fixed, not in the original 4 confirmed issues but surfaced by
this milestone's own review/verification:**

- **`aria-disabled` on the sidebar's "Help & Support" link** (M4
  report recommendation #6). Added `aria-disabled="true"` (which
  triggers the existing `sidebarMenuButtonVariants` dimmed/
  `pointer-events-none` styling for free — no new CSS needed) plus an
  `onClick` `preventDefault()`. Verified both interaction paths
  independently: mouse clicks are blocked by `pointer-events: none`
  (confirmed via computed style — Playwright's own click simulation
  correctly fails to land on the disabled element), and keyboard
  `Enter` activation is blocked by `preventDefault()` (`pointer-events`
  doesn't affect keyboard-triggered synthetic clicks, so this second
  layer is necessary, not redundant).
- **`EmptyState`'s heading level** (new finding — not in the M4
  report at all). Widened the `axe-core` audit scope to include
  `best-practice`-tagged rules, per the M4 report's own recommendation
  #3, and it immediately surfaced two real violations the narrower
  WCAG-only scope had missed: `heading-order` (dashboard, and by
  extension every placeholder page — `EmptyState`'s hardcoded `<h3>`
  skipped a level after `PageHeader`'s `<h1>`) and
  `page-has-heading-one` (the 404 page had no `<h1>` at all).
  Fixed by making `EmptyState`'s title heading level a `titleAs` prop
  (default `"h2"`, the correct level after a `PageHeader`), with
  `not-found.tsx` and `(app)/error.tsx` passing `titleAs="h1"`
  explicitly, since `EmptyState` is their only heading. Re-verified
  across all 7 routes (6 shell pages + 404): 0 violations, correct
  `h1 → h2` hierarchy everywhere, confirmed via both `axe-core` and a
  direct heading-tag dump per page.

**Process notes**

- This milestone's own verification directly validated the M4
  report's "audit rule-tag scope has a blind spot" risk finding — not
  hypothetically, but by widening the scope and immediately finding
  two more real violations. The recommendation was correct, and acting
  on it caught real bugs.
- One test-harness false positive during verification (a stray
  `SyntaxError` console message on one page load) was investigated and
  confirmed not reproducible via two clean isolated re-runs before
  being dismissed — not waved away on the first look.
- Followed the production-mindset guidance given ahead of this
  milestone: explained the mobile-search alternatives-and-reasoning
  before writing the code (not after), and used static code review for
  the parts of this work that were genuinely reviewable that way,
  reserving the dev-server/Playwright/axe cycle for verifying the
  actual behavioral fixes (landmarks, heading order, disabled-link
  interaction) — which is implementation verification, not reflexive
  review-time tooling.

**Verification** — `typecheck`, `lint`, `build` all pass (see
Validation below). Playwright across desktop/mobile viewports;
`axe-core` widened to `best-practice` tags across all 7 routes: 0
violations. Cleaned up all temporary verification scripts and
uninstalled temporary packages afterward, as established practice.

## 2026-07-10 — Milestone 4: Product Shell

Full application shell: sidebar, header, configuration-driven
navigation, routing, and UX states. No business functionality — every
route is a placeholder. Full reasoning behind the non-obvious decisions
lives in `docs/adr/0002-product-shell.md` and
`docs/ENGINEERING_JOURNAL.md`; this entry is the what/when.

**Discrepancy noted before starting.** The brief referenced
`docs/MILESTONE_REPORT_M3_1.md`, which doesn't exist as a separate
file — Milestone 3.1's findings were amended directly into
`docs/MILESTONE_REPORT_M3.md` (that milestone's actual doc-update
instructions listed `MILESTONE_REPORT_M3.md`, not a new `_M3_1` file).
Treated that file's update note as the M3.1 report for this review.

**New `ui/` primitives** — `sidebar`, `sheet`, `separator` (+
`hooks/use-mobile.ts`), plus `dropdown-menu`, `popover`, `breadcrumb`.
The first three were **not** installed via `npx shadcn add sidebar`
directly — that command reported it would overwrite `button.tsx`,
`input.tsx`, `skeleton.tsx`, and `tooltip.tsx`, all carrying Milestone
3.1's accessibility hardening. Inspected `sidebar.tsx`'s real source
via `--view` first, confirmed it only does normal
`import { Button } from "@/components/ui/button"`, and hand-extracted
the four genuinely new files instead. `dropdown-menu`/`popover`/
`breadcrumb` had no such conflict (pure creates) and were added
normally. Full investigation in `docs/ENGINEERING_JOURNAL.md`.

**Fixed two accessible-name gaps in the vendor source itself** while
integrating: `SidebarTrigger` and `SheetContent`'s close button both
relied on an `sr-only` text child rather than `aria-label`, which
fails to typecheck against this project's stricter `Button` (the
Milestone 3.1 discriminated union). Added explicit `aria-label` to
both during integration — confirms the enforcement catches real gaps,
not just hypothetical ones.

**Navigation model** — `src/lib/navigation.ts`: one config array
(`{ title, href, icon }`, grouped), read by both `AppSidebar`
(rendering) and the header's `getNavTitle()` (breadcrumbs). Future
modules cost one config entry + one route folder; verified true by
construction, not just asserted.

**Theme toggle** — installed `next-themes` rather than hand-rolling
`localStorage` + `useEffect`, specifically to avoid a flash-of-wrong-
theme on load (a real SSR/hydration correctness problem, not a
style preference). `defaultTheme="dark"` per the design brief's
"dark mode first" priority; `enableSystem` still respects OS
preference on first visit. Added `suppressHydrationWarning` to
`<html>` (required — `next-themes` intentionally sets the theme class
after hydration via an injected script, which next-themes documents
as needing this).

**Routing** — `src/app/(app)/` route group: `layout.tsx` (wires
`ProtectedLayout` → `DashboardLayout`), `loading.tsx` (skeleton,
scoped so only the content region re-renders during navigation — the
shell chrome stays mounted across route changes), `error.tsx` (client
error boundary), and six pages (`/`, `/dashboard`, `/content`,
`/wordpress`, `/analytics`, `/settings`), each `PageHeader` +
`EmptyState`. Moved the old root `page.tsx` into the group (can't have
both — they'd collide on `/`). `src/app/not-found.tsx` stays **outside**
the group deliberately — a 404 for a completely unmatched URL
shouldn't assume the dashboard shell applies.

**`ProtectedLayout`** — currently a pass-through, wired into the route
group now so Milestone 8 (Authentication) only has to implement a real
check in one place rather than retrofit every route.

**Bugs found via interaction testing (not caught by `tsc`/lint/build)**
- Opening the user-menu `DropdownMenu` crashed at runtime:
  `MenuGroupContext is missing` — `DropdownMenuLabel` was used outside
  a `DropdownMenuGroup` wrapper. Base UI enforces the ARIA grouping
  relationship via context and throws; Radix-influenced assumptions
  (standalone labels "just work") didn't transfer. Fixed by wrapping
  the label + items in `DropdownMenuGroup`.
- The 404 page's `<Button render={<Link href="/" />}>` logged a Base
  UI warning: rendering as a non-`<button>` while the component still
  expects native button semantics. Fixed with `nativeButton={false}`,
  now the documented pattern for composing `Button` as a navigation
  link. Checked the rest of the codebase for the same pattern — no
  other instances needed it (`SidebarMenuButton`'s different
  `useRender`-based composition isn't affected).
- Both found and fixed via the same temporary-preview-and-revert-style
  Playwright protocol used since Milestone 3, this time run directly
  against the real shell routes (interaction clicks + console-error
  capture), not a throwaway page.

**Self-review finding: missing `<nav>` landmark.** The `sidebar`
primitive renders its content in plain `<div>`s, not a `<nav>` element.
`axe-core` didn't flag it as a hard violation (WCAG 2.4.1 can be
satisfied without a literal `<nav>`, and the `<main>`/`<header>`
landmarks were already present), but "Semantic HTML" was an explicit
self-review checklist item, and a persistent site-navigation region is
exactly what `<nav>` is for. Changed the content-bearing element in
all three `Sidebar` render branches (desktop, mobile/`Sheet`,
`collapsible="none"`) from `<div>` to `<nav aria-label="Main">`.
Re-verified: 0 axe violations, identical screenshot, one `nav[aria-label="Main"]`
landmark present.

**Verification** — Playwright across desktop (1440px), tablet (820px),
mobile (390px, drawer open/closed), and ultra-wide (2560px, content
stays `max-w-7xl`-constrained rather than stretching); sidebar
collapse/expand; theme toggle; notifications popover (reuses
`EmptyState`); user menu (disabled placeholder items rendered as
genuinely disabled, not fake-clickable); keyboard tab order (7
sidebar links → header trigger, matching DOM/visual order); active
route highlighting (confirmed via the actual rendered `data-active`
attribute, not a wrong first-guess selector). `axe-core`: 0 violations
across 6 scenarios (light/dark, desktop/mobile, nested route, 404,
open dropdown) both before and after the `<nav>` fix. `typecheck`,
`lint`, `format:check`, `build` all pass — production bundle is 814 B
per route + 103 kB shared, unchanged in shape from Milestone 3 (no
route pulls in more than it needs).

**Documentation** — `docs/adr/0001-design-system.md` (retroactive,
capturing Milestones 2/3/3.1 decisions) and `docs/adr/
0002-product-shell.md` (this milestone's decisions) created;
`docs/ENGINEERING_JOURNAL.md` created with both backfilled entries
(the `render`-vs-`asChild` and contrast-measurement investigations from
earlier milestones) and this milestone's five real investigations.

## 2026-07-10 — Milestone 3.1: Design System Hardening

Patch milestone addressing every finding from the Milestone 3 report
(`docs/MILESTONE_REPORT_M3.md`, since amended in place with a
resolution note). No new components, pages, or features.

**Method, not just fixes.** Rather than trust the earlier axe-core
numbers or eyeball new values, built a small reusable measurement
script: resolves any CSS color (`oklch()`, `color-mix()`, ...) to
concrete sRGB bytes via a headless Chromium Canvas2D context — this
matters because `getComputedStyle` preserves `oklab()`/`color-mix()`
function notation in its serialization rather than resolving to `rgb()`,
which broke a first attempt at this script. Manually composited
alpha-tinted "badge" backgrounds (Tailwind's `bg-success/10` etc.) over
their real backdrop using the standard "over" formula, then applied the
WCAG relative-luminance contrast formula directly — same math a real
screen produces, not an approximation.

**WCAG contrast — tokens only, per the milestone's explicit
instruction.**
- Measured baseline (matched the Milestone 3 axe-core numbers exactly,
  cross-validating the new method): `success` 3.73:1, `success` on its
  own badge tint 3.32:1, `warning` 2.27:1, `warning` on tint 2.09:1 —
  all failing 4.5:1. Also found two failures axe's single-page audit
  hadn't surfaced, because nothing in that page happened to render the
  exact pairing: `destructive` on its own `/10` badge tint (3.97:1) and
  `muted-foreground` on `muted` background (4.35:1).
- Auto-searched for the lightest (closest to original) OKLCH lightness
  per hue that still clears 4.5:1 against **both** the plain background
  and the tinted-badge composite, then added a small safety margin.
  Light-mode-only changes (dark mode already passed everything):
  `success` L 0.6→0.5, `warning` L 0.75→0.52, `destructive` L
  0.577→0.52, `muted-foreground` L 0.556→0.54.
- Found a second-order issue while verifying: the darkened `warning`
  broke `warning-foreground` (near-black, tuned for the *old*, lighter
  fill) when used as solid-fill text — 3.53:1. Fixed by flipping
  `warning-foreground` to near-white, which also makes it consistent
  with how `success-foreground`/`destructive-foreground` already work
  (both were already near-white). One token-level fix resolved both
  the contrast failure and an existing color-role inconsistency.
- Sanity-checked the remaining grayscale tokens (`primary-foreground`,
  `secondary-foreground`, `accent-foreground`, `foreground`) for
  completeness — all pass with large margins (16:1–20:1), confirming
  the fix scope was correctly bounded to the chroma-bearing tokens the
  milestone named (Success, Warning, Error, Muted text).
- Re-ran the exact same axe-core audit from the Milestone 3 report
  against the corrected tokens, in a real rendered page: **0 violations
  in both light and dark mode** (was 2 light-mode violations before).

**Icon-only button accessibility.** Chose type-level enforcement over
the two conventions the milestone brief offered as examples: `Button`'s
props are now a discriminated union — when `size` is an icon size
(`icon`/`icon-xs`/`icon-sm`/`icon-lg`), `aria-label` becomes a required
prop, a compile-time `tsc` error if missing, not a runtime check or a
lint rule that could be silenced. No new `iconOnly` boolean prop added
— the existing icon-size variants already signal intent unambiguously,
so a second flag would be redundant. Verified the enforcement actually
works both directions with a throwaway `.tsx` file (deleted after):
`<Button size="icon"><Bell /></Button>` fails with a specific, clear
`tsc` error; adding `aria-label` compiles; a normal labeled button is
unaffected.

**Component/data-attribute consistency.**
- `Badge` didn't expose a `data-slot` DOM attribute the way every other
  primitive does — it uses Base UI's `useRender`/`mergeProps`
  polymorphic pattern with an internal `state.slot` instead of a plain
  element. Added a real `data-slot="badge"` attribute via `mergeProps`
  (confirmed via its source: external props always win on conflict, so
  this correctly overrides at the call site too). Required a small,
  documented TypeScript workaround — `mergeProps`'s parameter type
  doesn't declare arbitrary `data-*` keys even though the DOM allows
  them, so an inline object literal trips an excess-property-check
  error; extracting it to an explicitly-typed `const` first avoids that
  without an `any` cast.
- `StatCard` and `StatusBadge` (this project's own hand-written
  `common/` components, not upstream CLI code) were missing `data-slot`
  entirely — added `data-slot="stat-card"` / `data-slot="status-badge"`,
  overriding the `Card`/`Badge` primitives' own internal defaults the
  same way any consumer's override would.
- Confirmed via `grep -L` that every single component file now has
  `data-slot` — zero gaps left.
- `data-state`/`data-disabled`: nothing to change. These come from Base
  UI's own internals automatically and consistently; the
  `@custom-variant data-open`/`data-disabled`/etc. rules already added
  in Milestone 2 hook into that shared convention uniformly. Confirmed
  by review, not assumed.

**Badge review.** Decided: keep as generated, plus the `data-slot` fix
above. Its six variants (default/secondary/destructive/outline/ghost/
link) are already sufficient for `StatusBadge` to compose cleanly;
extending it further would be unused abstraction with no current
consumer.

**Design token audit.** Reviewed the full token list (typography,
spacing, radius, shadows, focus ring, duration, semantic/surface
colors, hover/disabled states, selection, scrollbar) for duplication —
checked programmatically (`awk` + `sort | uniq -d` across `:root`,
`.dark`, and `@theme inline`) rather than by eye. Zero duplicate
declarations found. Hover/disabled states and container widths remain
conventions (Tailwind's own utilities), not new tokens — reconfirmed
this is still the right call, not revisited.

**Process notes**
- All verification (contrast measurement, axe-core re-audit) used the
  same temporary-preview-page-then-revert protocol as Milestone 3 —
  confirmed an empty `git diff` on `page.tsx` against `HEAD` before
  finishing, same as before.
- `npm install --no-save <pkg>` followed by a second, separate
  `npm install --no-save <other-pkg>` call caused npm to prune the
  first package as "extraneous" (not in `package-lock.json`) — lost
  `@axe-core/playwright` partway through. Installing both packages in
  a single command avoids this; worth remembering for the next
  temporary-tooling install.

**Validation** — `typecheck`, `lint`, `format:check`, `build` all pass.
Amended `docs/MILESTONE_REPORT_M3.md` in place with a resolution note
rather than leaving it reading as if the findings were still open.

## 2026-07-10 — Milestone 3: Design System

**Scope decision (asked before starting).** The milestone brief listed
~19 `ui/` primitives and ~9 `common/` components as "Examples:" — not
phrased as a mandatory checklist, and in tension with this project's own
"never generate thousands of lines in one response" / iterative-scope
rule. Asked the user; agreed to build a **core set now, rest on demand**:
9 `ui/` primitives (Button, Input, Textarea, Label, Card, Badge, Avatar,
Skeleton, Tooltip) + a hand-built Typography scale, and 5 `common/`
composites (PageHeader, StatCard, StatusBadge, EmptyState, SearchInput).
Remaining primitives (Dialog, Table, Tabs, Sheet, Accordion, Popover,
Dropdown, Toast) get added in later milestones exactly when a feature
needs them.

**Component architecture**
- `src/components/ui/` — CLI-generated primitives (see below) + a
  hand-built `typography.tsx`.
- `src/components/common/` — reusable, business-agnostic composites.
  Consolidated the brief's overlapping `MetricCard`/`StatCard` examples
  into a single `StatCard` (avoiding "duplicate variants" the milestone
  itself warns against) with a `trend` prop (`up`/`down`/`neutral`,
  color-coded via `success`/`destructive`/`muted`).
- `src/components/layout/` — created empty (`.gitkeep` only), per the
  milestone's explicit "do not build layout components yet" boundary;
  reserved for Milestone 4.
- Removed the now-redundant top-level `src/components/.gitkeep` since
  the directory has real subdirectories.

**Generating the primitives**
- Re-verified `npx shadcn add` still resolves the `nova` preset
  correctly (flagged as a risk in the Milestone 2 report) — confirmed
  via `--dry-run` before generating anything.
- Generated Button, Input, Textarea, Label, Card, Badge, Avatar,
  Skeleton, Tooltip via the CLI. All use `data-slot` conventions, CVA
  variants, semantic color tokens (dark mode "free" as a result), and
  subtle/systematic transitions — no decorative or heavy motion,
  consistent with the design brief.
- Wired `TooltipProvider` into `src/app/layout.tsx` (the CLI's own
  post-install instruction) — this is provider wiring required for
  Tooltip to function at all, not a "layout component" in the
  Milestone 4 sense.
- Added a `loading` prop to `Button` during self-review (spinning
  `Loader2`, `disabled` + `aria-busy`) — "Loading" is an explicitly
  required component state per the brief, and Button is the primitive
  most likely to need it (form submits, async actions). Reuses
  existing Lucide + Tailwind infrastructure rather than adding a
  separate `Spinner` primitive, keeping scope to the agreed core set.

**Design tokens (`src/app/globals.css`)**
- Added `::selection` styling (soft primary-tinted highlight) and a
  minimal styled scrollbar (thin, subtle thumb using `--border`,
  darkens on hover) — both listed explicitly in the milestone's token
  checklist and previously absent.
- Container widths and hover/disabled states are handled as
  **conventions**, not new CSS tokens — Tailwind's default `max-w-*`
  scale and each component's own `hover:`/`disabled:` utilities
  already cover this; adding parallel custom tokens would just
  duplicate what Tailwind provides (documented in `PROJECT.md`).

**Typography scale** — `ui/typography.tsx`: a single polymorphic
`Typography` component (`variant` prop: `display`, `h1`–`h4`, `body`,
`body-sm`, `caption`, `label`, `code`; optional `as` override, sensible
default tag per variant). `body`/`body-sm` deliberately use
`text-sm`/`text-xs` rather than the more conventional
`text-base`/`text-sm`, to match the compact density the generated
primitives already established (`h-8` buttons, `text-sm` inputs) —
one consistent scale, not two competing ones.

**Iconography conventions** — Lucide React only. Documented four usage
categories (navigation, status, action, content) with default sizing
inherited automatically from each primitive's own icon-sizing selector.
Full detail in `PROJECT.md`.

**Visual verification (no pages allowed, verified anyway)**
- Milestone explicitly forbids building pages, but the project's own
  standing rule requires driving UI changes in a real browser before
  reporting them done. Resolved by temporarily replacing
  `src/app/page.tsx` with a preview rendering every new component,
  verifying it, then reverting — confirmed the revert is byte-identical
  to the committed version (empty `git diff`) before finishing.
- No project skill existed yet for running this app; used the `run`
  skill's browser-driven fallback pattern (`chromium-cli` wasn't
  available in this environment, so used Playwright directly — a
  temporary, not-saved `npm install --no-save playwright`, removed
  after verification).
- **Found and fixed a real bug this way**: composed `Tooltip` +
  `Button` using Radix-style `asChild`, which this Base UI-flavored
  shadcn setup doesn't support (confirmed via `TooltipTrigger`'s actual
  type — Base UI's polymorphism is a `render` prop accepting a
  `ReactElement`, not `asChild`). The mistake produced an invalid
  nested `<button>` inside `<button>` and a React hydration error that
  passed `tsc`, ESLint, and `next build` without complaint — only
  caught by actually rendering the page and reading browser console
  errors. Fixed by switching to `render={<Button ... />}`. This is
  worth remembering: **Base UI composition uses `render`, not
  `asChild`** — noted in `PROJECT.md` implicitly via the corrected
  pattern; call it out explicitly if this trips anyone up again.
- Verified: light mode, dark mode (forced via `.dark` class, since our
  dark mode is class-based, not `prefers-color-scheme`), tooltip
  hover-open interaction, keyboard `Tab` focus (confirmed a visible
  focus ring via computed `box-shadow`, not just visual inspection),
  and a 390px mobile viewport (header stacks, StatCards stack, buttons
  wrap — no overflow). Zero console/page errors after the fix.
- Along the way, clarified a subtlety worth documenting: the global
  `:focus-visible` fallback rule from Milestone 2 doesn't actually
  apply to any shadcn-generated primitive, because they all set
  `outline-none` and define their own `focus-visible:ring-3` box-shadow
  ring — and Tailwind's utilities layer always beats `@layer base`
  regardless of source order. Not a bug (every primitive has a visible,
  confirmed focus ring, just via a different mechanism); the global
  rule still protects any future plain HTML element. Documented in
  `PROJECT.md` so it doesn't look broken to a future reader checking
  the CSS in isolation.
- A `.next` build cache corruption (`EINVAL: invalid argument, readlink`
  on a OneDrive-synced path) blocked the first dev server start —
  resolved with `rm -rf .next`. Environment-specific (OneDrive sync
  interfering with Next's cache directory), not a code issue.
- Cleaned up thoroughly: temp verification scripts and the `playwright`
  package removed, `page.tsx` reverted (verified empty diff),
  `.next` cache untouched by git either way.

**Self-review findings (fixed within this milestone)**
- Button lacked a `loading` state despite it being an explicit
  requirement — added (see above).
- No other gaps found: every primitive already supports disabled,
  hover, focus-visible, keyboard navigation, and error states
  (`aria-invalid`) via the CLI's own generated code.

**Verification** — `typecheck`, `lint`, and `format:check` all pass;
all 14 new/modified component files under 110 lines (well within the
~300-line guideline, largest is `avatar.tsx` at 109).

## 2026-07-10 — Milestone 2: Project Foundation

**Dependencies installed**
- UI: `shadcn/ui` CLI (v4.13.0, dev-only), `@base-ui/react`,
  `lucide-react`, `class-variance-authority`, `clsx`, `tailwind-merge`,
  `tw-animate-css`.
- Forms/validation: `react-hook-form`, `zod`, `@hookform/resolvers`.
- State: `zustand`. Server state: `@tanstack/react-query`. Tables:
  `@tanstack/react-table`. Charts: `recharts`.
- Dev tooling: `prettier`, `prettier-plugin-tailwindcss`,
  `eslint-config-prettier`, `husky`, `lint-staged`.
- None of these are wired into application code yet — Milestone 2 is
  foundation only, no business features per scope.

**shadcn/ui configuration**
- The current shadcn CLI (v4.13.0) is a significant rework from the
  classic version: components are chosen from a `base`/`radix`
  primitive library and a named color preset (`nova`, `vega`, etc.)
  pulled from shadcn's registry, rather than the old
  `new-york`/`default` style + base-color prompts.
- Initialized with `--base base` (Base UI primitives — the CLI's
  current default, and the actively maintained successor built by the
  Radix/Floating UI team) and `--preset nova`, `--css-variables`,
  neutral base color, `src/` + TS + import aliases auto-detected.
- `init` auto-generated `src/components/ui/button.tsx` as a side
  effect — removed, since Milestone 2 scope explicitly excludes
  generating components (that's Milestone 3).
- The CLI also listed itself (`shadcn`) as a runtime `dependency` —
  moved to `devDependencies`; it's a codegen tool, never imported at
  runtime.

**Theme foundation (`src/app/globals.css`)**
- Kept the CLI's generated neutral OKLCH color scale (background,
  foreground, primary, secondary, muted, border, card, popover,
  accent, sidebar, chart-1..5) — it already matched the "neutral,
  dark-mode-first" brief. Dark mode is class-based (`.dark`), not
  `prefers-color-scheme`, so a manual toggle can be added later.
- Did **not** import the CLI's bundled `shadcn/tailwind.css` — it
  bundles genuinely useful infrastructure (data-state custom variants,
  accordion keyframes) alongside decorative "shimmer" text and
  "scroll-fade" mask utilities that contradict the brief's "no
  unnecessary animations" rule. Inlined only the needed variants/
  keyframes/`no-scrollbar` utility directly instead, avoiding an
  ongoing undocumented coupling to the CLI package's internal file.
- Added tokens the preset didn't include: `success`/`warning` (plus
  foreground pairs, light + dark), `destructive-foreground` (missing
  from the generated preset entirely), a soft shadow scale
  (`shadow-xs/sm/md/lg`, lighter in light mode / more opaque in dark),
  and `duration-fast`/`duration-slow` transition tokens.
- Added an explicit `:focus-visible` rule (2px ring, 2px offset) for
  keyboard-navigation accessibility, on top of the CLI's default
  low-emphasis `outline-ring/50`.
- Fixed a bug in the generated file: `--font-sans` was mapped to
  `var(--font-sans)` — a self-reference to a variable defined nowhere
  else, meaning `font-sans` would have silently fallen back to the
  browser default. Rewired it to the actual font stack.
- Fixed an inconsistency: `.dark` mode's `--sidebar-primary` carried a
  stray blue hue (`oklch(0.488 0.243 264.376)`) left over from the
  preset, while every other token was neutral grayscale — neutralized
  to match the same light/dark inversion pattern used by `--primary`.
- Tailwind's default spacing scale is used as-is (not reinvented) —
  Tailwind v4 already ships a sensible `--spacing` base unit.

**Typography**
- Geist (primary) + Inter (explicit fallback), both self-hosted via
  `next/font/google` — zero layout shift, no external font requests.
  Verified in the compiled build that Inter isn't eagerly preloaded
  (it's a pure CSS fallback, only fetched if Geist fails).

**Dev tooling**
- Prettier configured (`.prettierrc.json`, `.prettierignore`) with
  `prettier-plugin-tailwindcss` for automatic class sorting;
  `eslint-config-prettier` added to eliminate formatting-rule
  conflicts between ESLint and Prettier.
- Husky + lint-staged wired to a pre-commit hook: ESLint --fix +
  Prettier on staged JS/TS, Prettier on staged JSON/CSS/MD.
- Added `typecheck` (`tsc --noEmit`), `format`, `format:check` npm
  scripts. `typecheck` and `lint` are both CI-ready as standalone
  scripts.

**VSCode / environment / GitHub**
- `.vscode/settings.json` + `extensions.json`: Prettier as default
  formatter, format-on-save, ESLint auto-fix on save, Tailwind
  IntelliSense class-regex support for `cn()`/`cva()`. Recommended
  extensions only (ESLint, Prettier, Tailwind CSS IntelliSense) — no
  personal preferences.
- `.env.example` with the four requested placeholders, no secrets.
- `.github/`: bug report + feature request issue templates, PR
  template with a lint/typecheck/build checklist, `CODEOWNERS`.

**Issues found and fixed during self-review**
- `.gitignore`'s `.env*` pattern was also silently ignoring
  `.env.example` itself, which defeats its purpose as a committable
  template — added `!.env.example` negation.
- Confirmed Husky's internal `.husky/_/` helper scripts are
  self-ignored via their own `.gitignore` (only `.husky/pre-commit` is
  tracked), and that `package-lock.json` correctly reconciled `shadcn`
  as a dev dependency after the manual `package.json` edit.

**Known items for later milestones**
- `components.json`'s `"style": "base-nova"` ties `npx shadcn add` to
  a specific registry preset name; if the CLI renames/removes it in a
  future version, re-running `add` could behave unexpectedly. Low risk,
  worth a quick check before Milestone 3.
- `success`/`warning` OKLCH values are reasonable placeholders, not
  yet visually verified against real components — revisit once
  Milestone 3 adds badges/alerts that use them.
- A moderate `postcss` audit advisory remains, nested inside Next.js's
  own `node_modules` (noted in Milestone 1) — still not fixable
  without a breaking Next.js downgrade; recheck on the next Next.js
  patch release.

**Verification**
- `npm run typecheck`, `npm run lint`, `npm run format:check`,
  `npm run build` all pass. Dev and production servers both smoke
  tested; compiled CSS spot-checked to confirm the font stack and
  focus-ring rule compiled as intended.

## 2026-07-10 — Milestone 1: Project Initialization

- Scaffolded the project with `create-next-app`: Next.js 15 (pinned to
  15.5.20 — `latest` resolved to an unwanted 16.x canary), React 19,
  TypeScript, Tailwind CSS 4, ESLint 9, App Router, `src/` directory,
  `@/*` import alias.
- Removed auto-generated `AGENTS.md`/`CLAUDE.md` boilerplate that
  referenced the canary Next.js version — inaccurate once pinned to
  stable 15.5.20.
- Replaced the default starter homepage with a minimal placeholder and
  updated root metadata (title/description) to reflect WP Studio.
- Removed unused template SVG assets from `public/`.
- Built the feature-first `src/` skeleton: shared `components/`,
  `hooks/`, `lib/`, `services/`, `store/`, `types/`, `utils/`,
  `styles/`, plus `features/{dashboard,wordpress,content,analytics,
  settings,authentication}/` each with its own `components/`, `hooks/`,
  `services/`, `types/`, `utils/`. No business logic added.
- Added `docs/` with `PROJECT.md`, `ROADMAP.md`, `CODING_STANDARDS.md`,
  `DEVLOG.md`.
- Verified `tsc --noEmit`, `next lint`, and `next build` all pass.
