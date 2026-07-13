# Engineering Journal

Records the *reasoning* behind non-obvious technical problems — not a
changelog (see `DEVLOG.md` for that) and not a decision record (see
`docs/adr/` for accepted architectural decisions). This is where the
investigation itself is worth preserving, especially when the first
hypothesis was wrong.

---

## Future Backlog

A living, permanently-maintained list of known improvements identified
during implementation but deliberately not acted on in the milestone
that found them — either out of scope, not yet justified by real
usage, or blocked on a future milestone's infrastructure. Updated as
items are added, resolved, or reprioritized; not a chronological log
(see the dated entries below for that).

### High Priority

- **Mobile search loses focus on close** (found Milestone 4.1, still
  open). Closing the inline-expand mobile search returns focus to
  `document.body` instead of the search-toggle icon button. Real
  keyboard/screen-reader-user regression, not cosmetic.

### Medium Priority

- **Sidebar `isActive` uses exact match, not prefix match** (found
  Milestone 4.1, deliberately deferred). `/content/123` doesn't
  highlight the `Content` nav item. Needs a real decision on matching
  rules (e.g. should `/dashboard` prefix-match `/dashboard-settings`?)
  before implementing, not a reflexive `startsWith`.
- **Recent Drafts' deterministic failure is module state, not
  request state** (found Milestone 5). Resets on full page reload but
  not on client-side navigation within the app — fine for demoing the
  pattern once, but anyone navigating away and back mid-session won't
  see the error state repeat. Not worth solving for mock data; revisit
  if the real backend needs a similar demo/staging failure-injection
  mode.

### Low Priority

- **`src/styles/` exists but is empty and undocumented** (found
  Milestone 4). Either populate it with a real purpose or remove it —
  an empty, unexplained directory is a small but real ambiguity for
  the next person navigating the codebase.
- **`components.json`'s `iconLibrary`/style presets are hand-picked
  and undocumented as to why** (found Milestone 4). Low risk, but a
  future contributor changing them wouldn't know what would break.

### Deferred Priority

- **AI Assistant Preview has no real backend** (by design, Milestone
  5). Integration point documented inline in
  `src/features/dashboard/components/ai-assistant-preview.tsx` and in
  [[0003-dashboard-data-architecture]](adr/0003-dashboard-data-architecture.md);
  deferred to the milestone that adds AI integration, not tracked as a
  bug.
- **Mock service layer has no Laravel replacement yet** (by design,
  Milestone 5). Tracked as the explicit purpose of a future milestone,
  not backlog debt — see `docs/ROADMAP.md`.

---

## Interview Highlights

Five engineering decisions from Milestone 4.1 (Product Shell
Hardening), written to be talked through directly.

**1. Nested `<main>` landmarks — a bug static tooling can't see.**
*Problem:* `DashboardLayout` rendered `<main>` inside `SidebarInset`,
itself `<main>` — two landmarks, one nested in the other, invalid per
the HTML spec. *Chosen solution:* change the inner wrapper to a plain
`<div>`; `SidebarInset` already owns the semantic landmark. *Trade-offs:*
essentially none — a one-line, purely additive-safety fix with zero
behavioral or visual change (verified via before/after screenshot).
*Why this approach:* the alternative (removing `SidebarInset`'s `<main>`
and keeping the app's own) would fight the vendor primitive's own
contract for no benefit; deferring to the primitive's landmark and
removing the redundant one is the smaller, more correct diff.

**2. Choosing a mobile search pattern from four real options.**
*Problem:* search was `hidden` entirely below `sm` — not degraded, just
gone. The milestone brief offered four patterns: icon+expand, `Sheet`,
`Dialog`, `Popover`. *Chosen solution:* inline expand — a search icon
toggles the header row to a full-width input + close button, one
boolean `useState`, zero new dependencies. *Trade-offs:* less "dedicated
screen" polish than a `Sheet` would give; accepted, because search here
is presentational-only today (no results UI to give a dedicated screen
real weight to). *Why this approach:* `Sheet`/`Dialog` are built for
content needing a backdrop/dedicated space — using one for a single
input is more machinery than the job needs, and we'd be repurposing the
`Sheet` already pulled in for the sidebar's mobile drawer rather than
reaching for the simplest tool; `Popover` is anchored/floating by
design, awkward for something that wants full header width.

**3. Disabling a link needs two layers, not one.** *Problem:* the
sidebar's "Help & Support" link had no real destination but was fully
clickable. *Chosen solution:* `aria-disabled="true"` (which triggers
the existing `sidebarMenuButtonVariants`' built-in
`aria-disabled:pointer-events-none aria-disabled:opacity-50` styling
for free) plus an `onClick` `preventDefault()`. *Trade-offs:* two
mechanisms for one outcome looks redundant at a glance. *Why this
approach:* it isn't redundant — verified independently that
`pointer-events: none` blocks mouse clicks (Playwright's own click
simulation correctly failed to land on the element) but does **not**
block keyboard-triggered `Enter` activation, which only `preventDefault()`
stops. Confirmed both paths separately rather than assuming one implies
the other.

**4. `axe-core`'s rule-tag scope had a real blind spot — and widening
it immediately found real bugs.** *Problem:* the Milestone 4 report
predicted, as a *risk*, that scoping every audit to
`wcag2a`/`wcag2aa`/`wcag21a`/`wcag21aa` tags could hide `best-practice`-
tagged checks like `landmark-one-main`. *Chosen solution:* widened this
milestone's audits to include `best-practice` tags. *Outcome, not just
a decision:* it immediately surfaced two previously-invisible
violations — `heading-order` and `page-has-heading-one` — neither of
which were even hypothesized in the M4 report. *Why this matters for an
interview answer:* it's a concrete example of a predicted risk being
validated by acting on it, not just noted and left; the fix (see next
item) came directly from following through on a recommendation rather
than treating "0 violations" as a finished state.

**5. Making a shared component's heading level a prop instead of a
constant.** *Problem:* `EmptyState` hardcoded `<h3>`, which was wrong
in two different ways depending on context — skips a level after
`PageHeader`'s `<h1>` (six pages), and leaves zero `<h1>`s where
`EmptyState` is the only heading (404, error boundary). *Chosen
solution:* `titleAs?: "h1" | "h2" | "h3"`, default `"h2"` (correct for
the common case), with the two exceptions passing `"h1"` explicitly.
*Trade-offs:* a slightly wider public prop surface on a shared
component. *Why this approach over alternatives:* considered inferring
the right level automatically (e.g. via React Context reporting whether
a `PageHeader` is an ancestor) — rejected as solving a four-call-site
problem with infrastructure; an explicit prop is more code to type
once, but zero magic to debug later.

---

## 2026-07-13 — A static route with a clock-dependent greeting

**Problem.** `WelcomeSection` needs to show "Good morning/afternoon/
evening" and today's date — both are functions of `Date.now()`. The
dashboard route has no per-user data yet, so Next.js statically
generates it at build time. A greeting computed during that build
would be frozen: someone visiting weeks later would see whatever time
of day the build happened to run at, not their own.

**Investigation.** Considered forcing the route to render dynamically
per-request (`export const dynamic = "force-dynamic"`), which would
give every widget a fresh server clock — but that discards static
generation for eight widgets that have no per-request variance at all,
to fix a problem in one. Recalled the project already solved the
structurally identical problem for `ThemeToggle` (Milestone 4): a
value that must reflect the *client's* runtime state, not the
server's build/render state, rendered via a `mounted` guard
(`useState(false)` + `useEffect(() => setMounted(true))`) so the
initial paint is a neutral placeholder and the real value appears only
after hydration, avoiding a server/client text mismatch entirely.

**Decision.** Applied the same `mounted`-guard pattern to
`WelcomeSection`: render `Skeleton` placeholders for the greeting/date
until mounted, then compute them from the browser's own clock.

**Outcome.** Confirmed via Playwright across four viewport sizes —
zero console errors (in particular, no React hydration-mismatch
warning), and the rendered greeting matches the current local time in
every screenshot.

**Lessons learned.** "Prefer Server Components" and "this value must
reflect the visitor's actual runtime state" are two different axes,
not one — a value can need client-time evaluation without needing any
user interactivity. Recognizing this as *the same class of problem*
already solved elsewhere in the codebase (rather than treating it as
novel) avoided re-deriving the fix from scratch.

**Backlog items.** None — fully resolved within this milestone.

---

## 2026-07-13 — Demonstrating an Error state without flakiness

**Problem.** The milestone brief requires visibly demonstrating
TanStack Query's retry behavior and Error UI for Recent Drafts. A
mock service that always succeeds never exercises that code path at
all; a service that fails with `Math.random()` exercises it
unpredictably — a demo, code review, or screenshot pass might land on
a run where the failure never appears (or one where every widget
fails at once).

**Investigation.** The actual requirement wasn't "sometimes fail" but
"fail in a way a reviewer can reliably see, then recover." A
module-scoped counter (`draftAttempts` in `dashboard.service.ts`) that
fails calls 1 and 2, then succeeds from call 3 onward, is fully
deterministic per browser session. Paired that with a `retry: 1`
override on `useRecentDrafts` (global default is `retry: 2`) so the
automatic call + 1 retry (2 total attempts) are both consumed by the
guaranteed failures — the Error UI is guaranteed to render before any
manual action, rather than possibly auto-recovering silently on the
second automatic attempt.

**Decision.** Deterministic counter-based failure + per-query `retry`
override, not `Math.random()`.

**Outcome.** Verified with a dedicated Playwright script: on first
load, "Couldn't load drafts" is visible; clicking "Try again" (the
3rd call) shows the real draft list. Both assertions passed on the
first run.

**Lessons learned.** When a spec asks you to "demonstrate" an async
state, treat reliability of the demonstration itself as a requirement,
not just correctness of the underlying logic — a technically-correct
random failure that a viewer never happens to see has failed the
actual goal just as much as a bug would.

**Backlog items.** Failure state is module state, not request state
(see Future Backlog, Medium Priority) — acceptable for a mock-data
demo, revisit if a similar deterministic-failure pattern is ever
needed against a real backend.

---

## 2026-07-11 — Investigations backing the Interview Highlights above

Full investigation detail for two of the five decisions above, in the
Problem/Investigation/Decision/Outcome/Lessons format used elsewhere in
this journal.

### Nested `<main>` and the value of an independent review pass

**Problem.** See Interview Highlights #1.

**Investigation.** Not found by this milestone's own work — found by
an *independent* review pass (`docs/MILESTONE_REPORT_M4.md`) reading
`DashboardLayout` and `SidebarInset` fresh, rather than re-trusting the
Milestone 4 session's own "0 violations" `axe-core` result. Confirmed
via `page.locator("main").count()` returning 2 on shell pages
pre-fix, 1 post-fix, across every route.

**Decision.** Change `DashboardLayout`'s inner wrapper from `<main>` to
`<div>`.

**Outcome.** Verified 1 `<main>` per route across all 7 (6 shell pages
+ 404), confirmed via both a landmark count check and a widened
`axe-core` pass.

**Lessons learned.** The same lesson as this project's earlier entries,
from a different angle: this time it wasn't a runtime crash a
browser-click would catch, and it wasn't a WCAG-tagged `axe-core`
violation either — it took a human reading the actual rendered HTML
structure against the HTML spec. Automated tooling and manual review
catch different classes of defect; neither replaces the other.

### `axe-core`'s tag scope, and following through on a risk finding

**Problem.** See Interview Highlights #4.

**Investigation.** The M4 report's Risk #1 stated the hypothesis
(rule-tag scope could hide structural checks) but didn't test it — a
review has to stop somewhere. This milestone tested it: re-ran the
exact same audit setup with `best-practice` added to the tag list.

**Decision.** Adopt widened tags (`wcag2a`, `wcag2aa`, `wcag21a`,
`wcag21aa`, `best-practice`) as the standard going forward, not a
one-off for this milestone.

**Outcome.** Two real violations surfaced immediately —
`heading-order` and `page-has-heading-one` — both fixed (Interview
Highlights #5), then re-verified at 0 violations across all 7 routes
with the *same widened* scope, not a reversion to the narrower one.

**Lessons learned.** A documented risk that's never tested is just a
sentence. The value came from actually running the wider audit, not
from having predicted it might find something.

---

## 2026-07-10 — Base UI composition uses `render`, not `asChild`

**Problem.** Composing `Tooltip` + `Button` (`<TooltipTrigger asChild><Button>...</Button></TooltipTrigger>`)
produced an invalid nested `<button>` inside `<button>` and a React
hydration error — one that `tsc`, ESLint, and `next build` all passed
without complaint.

**Investigation.** `asChild` is Radix UI's polymorphism convention,
and this project's shadcn setup uses Base UI, not Radix. Checked
`TooltipTrigger`'s actual prop type: `TooltipPrimitive.Trigger.Props`
had no `asChild` field at all. Traced the real mechanism to
`BaseUIComponentProps`'s `render` prop — "Accepts a `ReactElement` or a
function that returns the element to render," documented directly in
Base UI's own type comments.

**Decision.** Switched to `<TooltipTrigger render={<Button ... />} />`
— Base UI merges the trigger's own props/ref into the render element
rather than wrapping it, producing one final `<button>`.

**Outcome.** Hydration error gone, confirmed via a real rendered page
(not just the type system). Documented as the standard composition
pattern in `docs/PROJECT.md`; every subsequent `render`-prop usage
across Milestones 3–4 followed this correctly on the first attempt.

**Lessons learned.** Training-data assumptions about "how shadcn
components compose" don't transfer across the Radix→Base UI switch.
When a composition pattern looks idiomatic but behaves wrong, check
the actual prop type before assuming the runtime is buggy.

---

## 2026-07-10 — Measuring color contrast correctly (not `getComputedStyle`)

**Problem.** Needed to verify `success`/`warning`/`destructive` token
contrast against WCAG AA (4.5:1) empirically, not by eye. First attempt:
render a probe element with the color, read `getComputedStyle(el).color`,
parse as `rgb(...)`.

**Investigation.** The parse failed — `getComputedStyle` returned
`oklab(0.6 -0.111432 0.0669549 / 0.1)`, not `rgb(...)`. CSS Color 4's
computed-value serialization preserves the *function* used
(`oklch()`/`color-mix()` results serialize as `oklab()`), it doesn't
force-resolve to sRGB just because a value was requested. Any
contrast script relying on regex-parsing `getComputedStyle`'s `color`
would silently produce wrong (or in this case, crashing) results for
any modern CSS color function.

**Decision.** Switched to a Canvas2D probe: set `ctx.fillStyle` to the
color expression, `fillRect`, then `getImageData` — canvas always
paints in concrete sRGB bytes regardless of input color space, because
that's what actually reaches the screen.

**Outcome.** Produced exact, verifiable contrast ratios (cross-checked
against axe-core's own numbers — matched to 2 decimal places),
including alpha-composited "badge tint" backgrounds via manual
over-compositing, which `getComputedStyle` can't do at all (it returns
the un-composited color, not what's visually rendered).

**Lessons learned.** For anything measuring *rendered* color (contrast,
pixel sampling, visual diffing), go through a rendering API (Canvas,
screenshot), not the CSSOM — the CSSOM describes the value as
authored/computed, not as painted.

---

## 2026-07-10 — `shadcn add` will silently overwrite customized files

**Problem.** Milestone 4 needed the `sidebar` primitive. `npx shadcn
add sidebar --dry-run` reported it would overwrite `button.tsx`,
`input.tsx`, `skeleton.tsx`, and `tooltip.tsx` — all already modified
with Milestone 3/3.1 accessibility work, most critically `Button`'s
compile-time `aria-label` enforcement.

**Investigation.** The CLI bundles every dependency a component needs
as part of its registry entry and re-stamps its own canonical copy on
`add`, with no diffing against local changes beyond a raw file-content
overwrite. Used `--view src/components/ui/sidebar.tsx` to read the
actual source before deciding: it does plain `import { Button } from
"@/components/ui/button"` — a normal import, not anything requiring
the CLI's exact vendor bytes.

**Decision.** Extracted the four genuinely new files (`sidebar.tsx`,
`sheet.tsx`, `separator.tsx`, `use-mobile.ts`) by hand via `--view`,
never running `add` against files that already existed. Along the way,
found and fixed two accessible-name gaps *in the vendor source itself*
— `SidebarTrigger` and `SheetContent`'s close button both relied on an
`sr-only` text child rather than `aria-label`, which fails to
typecheck against this project's stricter `Button`.

**Outcome.** `sidebar`/`sheet` integrated with zero loss of Milestone
3.1's work, verified via `grep -c "aria-label"` on `button.tsx`
before/after. The type-level enforcement caught real gaps in
shadcn's own generated code, not just hypothetical misuse.

**Lessons learned.** Never run `shadcn add` against an already-
customized file without `--dry-run` first — now a standing project
rule (`docs/adr/0001-design-system.md`). Second: a strict, compile-time
accessibility contract earns its complexity the moment it catches
something in code you didn't write yourself.

---

## 2026-07-10 — Base UI's `Menu.GroupLabel` requires `Menu.Group`

**Problem.** Opening the header's user-menu `DropdownMenu` crashed at
runtime: `Base UI: MenuGroupContext is missing. Menu group parts must
be used within <Menu.Group> or <Menu.RadioGroup>.` Static checks
(`tsc`, ESLint, `next build`) had all passed.

**Investigation.** `DropdownMenuLabel` (`MenuPrimitive.GroupLabel`) was
used directly inside `DropdownMenuContent`, without a
`DropdownMenuGroup` wrapper. In Radix (and casual assumption), a
standalone label often works without an explicit group; Base UI
enforces the ARIA grouping relationship at runtime via context and
throws rather than degrading silently.

**Decision.** Wrapped the label + items in `<DropdownMenuGroup>`.

**Outcome.** Confirmed fixed by re-running the same interaction test
that caught it (Playwright click + console-error capture) — the crash
is gone and the semantic grouping is now also more correct (the label
is properly associated with its items for assistive tech, not just
visually adjacent).

**Lessons learned.** Third instance this session of the same pattern:
a runtime-only failure invisible to every static check, caught only by
actually opening the menu in a browser. The pattern is now explicit
project practice, not incidental — every new interactive primitive
gets clicked, not just typechecked.

---

## 2026-07-10 — `nativeButton` and composing `Button` as a link

**Problem.** The 404 page's `<Button render={<Link href="/" />}>Back
to Overview</Button>` logged a Base UI warning in the browser console
(page still worked, but the warning is a real signal): rendering as a
non-`<button>` while the component still expects native button
semantics can affect forms/accessibility.

**Investigation.** Base UI's `Button` defaults to assuming it renders
a native `<button>` (`nativeButton: true`) for correct implicit ARIA
role and form-submission behavior. Overriding via `render` to output
an `<a>` changes that without telling the component, which is exactly
what the warning flags.

**Decision.** Added `nativeButton={false}` to explicitly declare "this
is intentionally not a native button" — checked the rest of the
codebase (`grep`) for the same `Button render=` pattern to see if
other instances needed the same fix; none did (the only other
button-as-link usage, in `AppSidebar`, uses `SidebarMenuButton`'s
different, unaffected `useRender` pattern).

**Outcome.** Warning gone, re-verified via the same Playwright
console-error capture used throughout this milestone.

**Lessons learned.** "Composing a Button to act as a navigation link"
is common enough (call-to-action buttons that navigate) that this is
now a documented pattern, not a one-off fix: pair `render={<Link .../>}`
with `nativeButton={false}` whenever `Button` is deliberately used as
a link.