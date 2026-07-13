# Milestone 5 Report

## Date

2026-07-13

---

## Objective

Build the Dashboard Experience — nine widgets on realistic mock data,
demonstrating a real Loading/Empty/Error/Success async-state
architecture (TanStack Query + a mock service layer) that a future
Laravel API can replace without component-level rewrites. No backend,
auth, or AI integration. Reuse the Design System and Product Shell
built in Milestones 3–4.1.

---

## Executive Summary

The Dashboard Experience is functionally complete, ships zero
lint/typecheck/build failures, and its data/state architecture (mock
service layer → TanStack Query hooks → widgets) is genuinely
well-reasoned and Laravel-ready. The deterministic Recent Drafts
retry/error demonstration is a standout piece of engineering — reliable
by design rather than dependent on random chance. Documentation
(ADR 0003, Engineering Journal, DEVLOG) is thorough and, on
verification, accurate.

Static and runtime review found **one real, non-trivial accessibility
regression** — the dashboard's server-rendered/static HTML ships with
**zero `<h1>` elements**, confirmed in the actual production build
artifact, not just a dev-mode guess — plus three smaller but genuine
findings: an inconsistently-applied disabled-control pattern, one
dead/unwired feature (notification count), and one unused export. None
of these are architectural; all are contained, single-component fixes.
Full detail below.

**This review's own first verification pass produced several false
findings (404s, missing chart, missing h1 that never resolved, failed
retry demo) that turned out to be caused by testing against a stale
zombie dev-server process on the wrong port — not defects in the
code.** Re-verified against the correct server and reported only what
reproduced consistently. Documented here in the interest of the same
"verify, don't assume" standard this report holds the codebase to.

---

## Engineering Summary

**Data flow, verified by reading the code, not trusting the ADR's
description.** `src/services/mock/dashboard.service.ts` — seven
`delay()`-wrapped async functions. `src/features/dashboard/hooks/` —
**six** `useQuery` wrappers. That's a mismatch worth naming: one
service function, `getQuickActions()`, has no corresponding hook and
is never called anywhere in the codebase (confirmed via
project-wide grep — zero call sites beyond its own definition). See
Technical Debt #3.

**TanStack Query configuration** — `QueryProvider` creates the
`QueryClient` inside `useState` (correct: avoids cross-request cache
leakage in an SSR context), sets `staleTime: 60s` and `retry: 2` with
exponential backoff once, globally. `useRecentDrafts` correctly
overrides to `retry: 1` — verified this isn't cosmetic: a live timing
trace (polling the widget's rendered text every 300ms) shows Loading
until ~1.8s, then Error, staying in Error consistently through 4.8s —
the override is load-bearing, not decorative.

**Zustand** — one store (`useNotificationStore`), correctly minimal in
scope per the ADR's stated reasoning (Analytics Preview's range stays
local `useState`, reasonably so — nothing else reacts to it). However:
**the store is entirely disconnected from the app.** `setCount` is
defined but has zero call sites anywhere in `src/` (verified by grep).
The header's notification badge, the "you have N updates" message, and
the "Mark all as read" button are consequently unreachable in every
build — always rendering the zero-notifications `EmptyState` branch.
The store's own doc comment asserts "the dashboard's activity query...
sets the count once data loads" — this describes wiring that does not
exist in the codebase. See Technical Debt #4.

**Server/Client boundary** — verified by grep across all nine widget
files: eight are `"use client"` (all `useQuery` consumers), one
(`QuickActions`) is a Server Component, correctly, since it fetches
nothing. This is the necessary consequence of `useQuery` being a
client-only hook, not an oversight — consistent with the ADR's own
stated trade-off.

**React patterns** — no anti-patterns found: no missing `key`s, no
unstable inline object props causing avoidable re-renders worth
flagging at this scale (largest list is 5 items), no unnecessary
`useEffect`, no Context misuse, no prop drilling. `WelcomeSection`'s
`mounted`-guard reuses the existing `ThemeToggle` pattern correctly for
its *stated* purpose (avoiding a text mismatch) — but applying that
pattern to the page's sole `<h1>` has a consequence the `ThemeToggle`
precedent didn't have to consider. See Accessibility Summary.

**Duplication** — one real instance was found and fixed within the
milestone itself (`SiteStatus`/`ServiceStatus` → badge mapping,
identical in `wordpress-overview.tsx` and `system-health.tsx`,
extracted to `status-meta.ts`) — verified via `grep` that both files
now import from the shared location and no duplicate `Record` literal
remains. Good self-review discipline, real evidence of it working.

---

## Product Summary

Ratings reflect a fresh, critical pass — desktop, tablet, and mobile
screenshots reviewed directly, not assumed from the build session.

| Category | Rating | Notes |
| --- | --- | --- |
| First Impression | ★★★★☆ | Greeting + description + date + nine widgets read as a real product dashboard, not a placeholder — a genuine step up from Milestone 4's `EmptyState`-only pages. Docked one star: the greeting is blank for a brief instant on every load (skeleton in its place) — see Accessibility Summary; a first impression that starts with an empty heading is a real, if small, cost. |
| Professional Appearance | ★★★★★ | Consistent with the established dark-mode-first, Vercel/Linear-inspired visual language. No decorative excess, spacing is even, typography scale used correctly throughout. |
| Information Hierarchy | ★★★★☆ | Welcome → KPIs → Actions → Activity/Overview → Analytics/Drafts → AI/Health reads as a sensible priority order. Docked one star for the same heading-timing issue — hierarchy depends on JS execution, not just visual layout. |
| Visual Consistency | ★★★★★ | Every widget is a `Card` with the same header/content structure; icons, badges, and typography variants are used uniformly across all nine. |
| Navigation Flow | ★★★★★ | No regressions to the Product Shell; breadcrumb, sidebar, and header all function exactly as Milestone 4.1 left them. |
| Widget Organization | ★★★★☆ | The two/three-column responsive grid groupings (Activity+Overview, Analytics+Drafts, AI+Health) are sensible pairings by information weight. Docked one star: `Quick Actions` nests four fake "cards" inside one real `Card`, the only widget with this two-level pattern — a minor, not harmful, inconsistency in an otherwise uniform "one widget, one Card" structure. |
| Dashboard Readability | ★★★★★ | Numbers, trends, and statuses are scannable at a glance; color-coded `StatusBadge`/trend arrows do real work here. |
| Loading Experience | ★★★★★ | Verified directly, not assumed: KPI Cards' loading skeleton is confirmed visible immediately on navigation before data resolves. Two loading treatments (shape-matched `Skeleton` vs. centered `LoadingState`) are applied consistently by widget type. |
| Empty States | ★★★★★ | Every widget with a plausible empty case (KPIs, Activity, Analytics, Drafts) has one, reusing the existing `EmptyState` component — no hand-rolled empty markup anywhere. |
| Error States | ★★★★★ | Verified live, not just read: Recent Drafts genuinely transitions Loading → Error → (retry) → Success on a real timeline. Every data-backed widget has a consistent `EmptyState` + "Try again" pattern. |
| Responsive Behaviour | ★★★★★ | Verified via screenshots at 375/768/1440/1920px: KPI grid reflows 2→3→5 columns, two/three-column widget rows collapse to single-column below their breakpoints, no overflow or layout shift observed at any width. |

**Overall Product Experience: strong**, undercut narrowly by the same
underlying issue in three separate ratings above — the greeting/`<h1>`
not being present from the first paint.

---

## Architecture Summary

| Category | Rating | Reasoning |
| --- | --- | --- |
| Folder Structure | ★★★★★ | `src/features/dashboard/{components,hooks,types,utils}` + top-level `src/services/mock/`, `src/store/`, `src/lib/format.ts` — matches `CODING_STANDARDS.md`'s feature-first rule precisely; `format.ts` correctly placed top-level since it's generically reusable, not dashboard-specific. |
| Feature Architecture | ★★★★★ | Types are plain data (no icon/component refs), verified by reading `dashboard.types.ts` directly — presentation mapping (`KPI_ICONS`, etc.) correctly lives in each component, not the type file. |
| Component Boundaries | ★★★★☆ | Eight widgets are clean, single-purpose, appropriately-sized (60–165 lines, well under the ~300-line guideline). Docked one star for `QuickActions`' architectural inconsistency with its siblings (bypasses the service/hook layer that every other widget uses) — see Technical Debt #3. |
| Component Composition | ★★★★★ | Every widget composes existing `Card`/`StatCard`/`StatusBadge`/`EmptyState`/`LoadingState`/`Skeleton`/`Progress` — verified no widget hand-rolls markup an existing primitive already covers. |
| Code Reuse | ★★★★☆ | `status-meta.ts` extraction is genuine, reactive reuse (see Engineering Summary). Docked one star: the `SITE_STATUS_META`-style pattern (a `Record<Enum, {label, badge}>`) is repeated a third time inline as `DRAFT_STATUS_META` in `recent-drafts.tsx` — not wrong (only one consumer, extracting it would be premature), but worth watching if a second consumer appears. |
| Naming Consistency | ★★★★★ | `use-*.ts` hooks, `*-preview`/`*-overview`/`*-cards` component names, `get*`/`mock*` service naming — all consistent with prior-milestone conventions. |
| Service Layer | ★★★☆☆ | Well-designed in concept (Promise-returning, typed, `delay()`-wrapped) but the dead `getQuickActions()` export means the layer's own claimed universality ("every widget reads from the mock service layer") is false for 1 of 9 widgets. Docked two stars for a documented architectural claim that verification disproved. |
| Mock Layer | ★★★★★ | Deterministic Recent Drafts failure (module counter, not `Math.random()`) is genuinely well-engineered — verified via a real timeline trace, not just a pass/fail check. Fixture data is realistic and varied. |
| Developer Experience | ★★★★★ | ADR 0003 and the Engineering Journal's two new entries explain real *why*, not just *what* — verified their claims against the actual code (e.g., the `mounted`-guard reasoning, the `retry: 1` override reasoning) and both held up. |
| Scalability | ★★★★★ | Query keys namespaced (`["dashboard", ...]`), caching/retry defaults set once at the provider level so future features inherit them for free — verified by reading `QueryProvider`, not assumed. |
| Maintainability | ★★★☆☆ | Docked for the combination of findings below (dead code, inconsistent disabled pattern, missing `data-slot` on 2/9 widgets) — none individually severe, but collectively suggest the final cross-widget consistency pass this milestone's own self-review section claims to have done didn't catch everything it should have. |
| Future Laravel Compatibility | ★★★★★ | Swapping `dashboard.service.ts` function bodies for real `fetch()` calls requires no hook, component, or type changes — verified true by construction: every hook only imports its one service function and a query key, nothing else. |

**Architecture Readiness for Laravel / REST / Auth / WordPress
Integration / GraphQL / State Expansion:** Ready. Every finding in this
report is a presentation-layer or wiring defect, fixable within the
component it lives in — none require touching the mock/hook/type
layering itself. `ProtectedLayout` (Milestone 4) already wraps every
route; Authentication doesn't need dashboard changes. GraphQL, if
adopted later, would change `queryFn` bodies inside
`dashboard.service.ts`, not calling code.

---

## Accessibility Summary

| Area | Rating | Notes |
| --- | --- | --- |
| Semantic HTML | ★★★☆☆ | `dl`/`dt`/`dd` in WordPress Overview, `ol`/`li` for Activity, `ul`/`li` for Drafts — all correct. Docked two stars for the confirmed missing-`<h1>` finding below — a real, verified structural gap on the page that matters most. |
| Landmarks | ★★★★★ | No change to the Product Shell's landmark structure; unaffected by this milestone. |
| Keyboard Navigation | ★★★★☆ | Full tab-order trace captured live: sidebar → header → "My Workspace" → Analytics range toggles → chart → Drafts "Try again" → AI Assistant textarea/prompts → (dev-only tooling). Every genuinely-inert control (`Quick Actions`, `Generate`) is correctly skipped via native `disabled`. Docked one star: "My Workspace" is reachable and activatable via keyboard despite being visually and functionally disabled — see Technical Debt #2. |
| Focus Management | ★★★★★ | No new focus traps or loss-of-focus issues introduced; Analytics range buttons and Drafts' "Try again" behave as expected on activation. |
| ARIA | ★★★★☆ | `aria-pressed` on range toggles, `role="group"` with a label, `role="img"` + computed `aria-label` on the chart, `role="status"` on loading states — all correct and verified present in the rendered DOM. Docked one star for `aria-disabled` being used where it doesn't apply cleanly (see Keyboard Navigation). |
| Charts | ★★★★★ | Verified via direct DOM inspection: the chart renders a real `<svg>` (717×192, 2 `<path>` elements — stroke + fill) inside a `role="img"` container with a computed, data-accurate label. Not just "present" — confirmed it contains real content, not an empty shell. |
| Cards | ★★★★★ | Consistent `Card`/`CardHeader`/`CardContent` structure throughout; no nested-interactive-inside-interactive issues found. |
| Buttons | ★★★★☆ | Icon-only buttons retain the project's compile-time `aria-label` enforcement (unaffected by this milestone). Docked one star for the same "My Workspace" inconsistency counted above. |
| Responsive Behaviour | ★★★★★ | Verified via axe-core at 375/768/1440px, post-mount: **0 violations at all three**, widened `best-practice` tag scope included. |
| Screen Reader Experience | ★★★☆☆ | The missing initial `<h1>` is exactly the class of defect that hurts screen reader users disproportionately — "jump to main heading" has nothing to land on until React has mounted and run an effect, which does not happen at all for a no-JS or crawler context. This is the same category of defect (heading structure) Milestone 4.1 already spent real effort fixing project-wide; its recurrence here, in new code, is the most substantive finding in this report. |
| Color Contrast | ★★★★★ | Verified via axe-core (`color-contrast` rule, part of the standard WCAG AA tag set): 0 violations across all three viewports, post-mount. (This review's first pass reported a contrast violation — traced to testing against a stale dev-server process serving different, older code; not reproducible against the actual codebase. Documented under Executive Summary.) |
| Focus Visibility | ★★★★★ | No new focus-ring styling introduced; inherits the project's existing, previously-verified `focus-visible` treatment uniformly. |

**The central finding, stated plainly:** `WelcomeSection`'s greeting is
the page's only `<h1>`, and it does not exist in the DOM until a
`useEffect` fires client-side. Verified at three levels of increasing
authority:
1. `curl`'d dev-server HTML — 0 `<h1>` tags, no greeting text.
2. **The actual production build artifact** — `.next/server/app/dashboard.html` (the file real users and crawlers receive) — 0 `<h1>` tags, no greeting text.
3. `axe-core`, run immediately after `DOMContentLoaded` (before the mount effect has had time to run): reports `page-has-heading-one`. The same audit run again 2.5s later: 0 violations — the defect self-heals in every real browser session, which is exactly why the build-time verification session (which always waited before auditing) never caught it.

This is not a hypothetical edge case — it is what every crawler,
static analysis tool, or JS-disabled request actually receives for
this page. It is the same rule (`page-has-heading-one`) Milestone 4.1
fixed project-wide by adding `EmptyState`'s `titleAs` prop; this
milestone reintroduced a violation of that same rule in new code,
by a different mechanism.

---

## Performance Summary

**Bundle** — `/dashboard`: 119 kB route-specific, 241 kB First Load JS
(vs. 816 B / 103 kB for every placeholder route) — the overwhelming
majority of that delta is Recharts, TanStack Query, and nine client
widgets, all loaded eagerly in the initial route chunk. Route remains
statically prerendered (`○`) — confirmed in the build output.

**Hydration** — 0 hydration warnings/mismatches across three viewports
(direct console capture, not inferred). `WelcomeSection`'s
`mounted`-guard successfully avoids a text mismatch (its stated goal)
at the cost of the heading-timing issue above (an unstated
consequence).

**Rendering strategy** — 8 of 9 widgets are Client Components by
necessity (`useQuery`). No `React.memo`/`useMemo`/`useCallback` used
anywhere in the new code — correctly so at this scale (largest render
is a 5-item list); adding memoization here would be exactly the
premature optimization `CODING_STANDARDS.md` warns against.

**Suspense opportunities (not used, worth naming)** — every widget
manages its own `isPending`/`isError` branches rather than
`useSuspenseQuery` + a shared `<Suspense>` boundary. This is a
reasonable, defensible choice (per-widget Empty-state differentiation
is easier this way), not a defect — but it means 8 independent loading
branches instead of one shared boundary, worth a note for a future
milestone with more widgets.

**Code splitting / lazy loading (not used, worth naming)** — Recharts,
the heaviest new dependency, is eagerly bundled into the dashboard's
initial chunk rather than deferred via `next/dynamic`. For a
below-the-fold-on-mobile widget (Analytics Preview is 6th of 9 in
document order), this is a real, quantifiable opportunity: users who
never scroll to Analytics still pay its parse/execute cost today.

**Potential optimizations, not implemented:** dynamic-import
`AnalyticsPreview` to defer Recharts' cost off the critical path;
consider `useSuspenseQuery` if a future milestone adds enough widgets
that 8+ independent loading branches become genuinely repetitive.
Neither is urgent at 9 widgets and a 600ms mock delay.

---

## Technical Debt

1. **[High] Missing `<h1>` in server-rendered/static HTML until
   client-side mount** (`WelcomeSection`). Verified in the actual
   production build artifact, not just dev mode. Same rule
   (`page-has-heading-one`) Milestone 4.1 already fixed once,
   elsewhere. Fix is contained to `welcome-section.tsx` — e.g., always
   render the `<h1>` element (with a static fallback like "Welcome
   back") and swap only its *text* post-mount, rather than swapping
   the whole element for a `Skeleton`.
2. **[Medium] "My Workspace" button uses `aria-disabled` +
   `preventDefault()` instead of native `disabled`**, unlike
   `QuickActions`' correctly-disabled buttons built in the same
   milestone (whose own inline comment states the correct reasoning
   this button contradicts). Confirmed keyboard-focusable when it
   shouldn't be. `preventDefault()` on a `type="button"` click has no
   effect (no default action exists to prevent), so it's currently a
   no-op — the fix is simply removing `aria-disabled`/`onClick` and
   adding native `disabled`.
3. **[Medium] `getQuickActions()` is dead code** — exported, never
   called (verified via grep). `QuickActions` imports fixture data
   directly, bypassing the service+hook layer every other widget uses.
   Either wire `QuickActions` through `useQuery` like its siblings (for
   architectural consistency — even static data benefits from a
   consistent access pattern), or delete the unused export per
   `CODING_STANDARDS.md`'s explicit no-dead-code rule.
4. **[Medium] Notification count is fully unwired** — `setCount` has
   zero call sites anywhere in the codebase (verified via grep and
   runtime: the badge never appears, "Mark all as read" never renders
   in any real session). The store's own doc comment describes wiring
   that doesn't exist. Either wire it to something real (even a mock
   "3 new activity items" on dashboard load) or update the comment to
   accurately describe it as scaffolding for a future milestone, not
   an implemented behavior.
5. **[Low] `KpiCards` and `WelcomeSection` are missing `data-slot`**,
   the only 2 of 9 widgets without one — a real, if minor, regression
   of a convention this project already paid to establish (and fixed
   once before, for `Badge`, in Milestone 3.1).
6. **[Low] Unnecessary `cn()` call in `AnalyticsPreview`** —
   `className={cn("h-48 w-full")}` wraps a single static string with
   no conditional logic; `cn()` exists for merging/conditional classes
   and adds nothing here. Cosmetic only.
7. Carried over, unresolved by design (all Milestone 4/4.1 findings —
   `isActive` exact-match navigation, mobile search focus-return on
   close, `src/styles/` empty/undocumented, `components.json` preset
   coupling): unchanged this milestone, not touched, not worsened.
8. Carried over, by design (Milestone 5 scope): Recent Drafts' failure
   state is module/session-scoped, not request-scoped (see
   `docs/ENGINEERING_JOURNAL.md`). Acceptable for mock data.

---

## Risks

1. **Recurring heading-structure defects suggest this project needs a
   standing check, not a per-milestone catch.** This is the *second*
   `page-has-heading-one`-class defect (after Milestone 4.1's
   `EmptyState` fix) — both were invisible to a "render, wait, then
   audit" verification workflow. **Likelihood: Medium** (any future
   client-gated content has the same risk). **Impact: Medium**
   (accessibility + SEO, not a crash). **Mitigation:** the pattern this
   review used — running `axe-core` immediately post-`DOMContentLoaded`
   *in addition to* post-mount — is now demonstrated to catch this
   class of defect and costs one extra audit call; worth adopting as
   standing practice, along with a rule of thumb: never gate a page's
   only `<h1>` behind a client-only mount check.
2. **No automated regression testing** (carried forward from every
   prior report; Testing is Milestone 10). All four new findings in
   this report — the heading timing issue especially — are exactly the
   kind of defect a snapshot/interaction test suite would catch on
   every future change, that manual per-milestone review currently
   has to rediscover by hand each time. **Likelihood: High** (already
   materialized twice). **Impact: Medium-High**, growing with each
   milestone that ships without one. **Mitigation:** unchanged from
   prior reports — Milestone 10 remains the fix; until then, the
   before/after-mount `axe-core` double-check above is a cheap partial
   mitigation.
3. **Bundle growth from Recharts is currently unmanaged.** At 9
   widgets it's a non-issue (241 kB First Load JS is reasonable); at
   20+ widgets across future feature milestones without any lazy
   loading strategy, dashboard-adjacent routes could grow
   significantly. **Likelihood: Low** near-term, **Medium** by
   Milestone 9. **Impact: Low-Medium** (performance, not correctness).
   **Mitigation:** the `next/dynamic` code-splitting opportunity noted
   in Performance Summary; not urgent today.
4. `components.json` preset-naming coupling (carried over, still
   unverified against future CLI versions).

---

## Documentation Review

- **`docs/adr/0003-dashboard-data-architecture.md`** — thorough,
  follows the established Decision/Context/Alternatives/Chosen
  Solution/Trade-offs/Future Implications structure precisely. Its
  claims were spot-checked against the actual code (query defaults,
  `retry` override, Zustand scope, Server/Client split) and held up —
  **except** the "every widget reads from the mock service layer
  through a query hook" framing, which is not accurate for
  `QuickActions` (see Technical Debt #3). Worth a follow-up correction.
- **`docs/ENGINEERING_JOURNAL.md`** — two new investigation entries
  (clock-dependent greeting, deterministic-failure demo) both verified
  accurate against the code and runtime behavior. The new permanent
  "Future Backlog" section is well-organized (High/Medium/Low/Deferred)
  and should be updated with this report's new findings (see Future
  Backlog Recommendations below) — it does not yet mention the heading
  or `aria-disabled` findings, since they weren't known when it was
  written.
- **`docs/PROJECT.md`** — the new "Dashboard Experience" section
  accurately describes the widget list, data flow, and state
  boundaries. Known Limitations already lists the mock-data and
  AI-preview limitations; does not yet list this report's new findings
  (expected — they weren't known at write time).
- **`docs/ROADMAP.md`** — Milestone 5 marked complete with an accurate,
  specific summary; Milestone 6 correctly annotated that its
  foundational work already shipped here.
- **`docs/DEVLOG.md`** — detailed, matches the actual file list and
  widget behavior verified in this review (spot-checked the retry
  mechanism description and the Server/Client split claim — both
  accurate).

**Overall: documentation is accurate to the code as written**, with
one overstated claim (service-layer universality) that this review's
own verification caught — exactly the kind of gap an independent pass
exists to find.

---

## Interview Highlights

Five strongest engineering decisions from Milestone 5:

**1. Deterministic, not random, failure injection for the retry demo.**
*Problem:* demonstrating TanStack Query's retry/Error UI needs a
failure to actually occur, reliably, without becoming permanently
broken. *Solution:* a module-scoped counter fails exactly the first
two calls to `getRecentDrafts()` per session, paired with a per-query
`retry: 1` override so both automatic attempts are guaranteed
exhausted before Error UI renders. *Trade-offs:* module state, not
request state — resets on full reload, not client navigation; accepted
as fine for a mock-data demo. *Why chosen:* random failure risks a
demo, screenshot, or review pass landing on a run where the bug never
appears. *Why it impresses:* it shows the engineer treating
"demonstrate an async state reliably" as a real requirement with its
own design problem, not just "make the function sometimes throw" —
and the fix was verified with an actual state-transition timeline
(Loading at 0ms → Error at ~1.8s, stable through 4.8s), not just a
pass/fail check.

**2. Reconsidering and rejecting the brief's own suggested Zustand use
case.** *Problem:* the milestone brief listed "Dashboard Filters" as
an example of Zustand-worthy state. *Solution:* recognized that no
widget besides Analytics Preview has a time dimension, and nothing
reacts to that range — so it's local `useState`, not global state; the
one store actually added (notification count) is justified by two
unrelated component trees needing the same value. *Trade-offs:* less
"complete" adherence to the brief's literal examples. *Why chosen:* a
shared store nothing consumes is worse than no store — YAGNI applied
correctly even against an explicit suggestion. *Why it impresses:* it
demonstrates judgment over compliance — recognizing when a
spec's own example doesn't fit the actual requirements, and being able
to articulate why, rather than building it because it was named.

**3. Confining a client-time dependency to the smallest possible
component.** *Problem:* a statically-generated page needs a
visitor-local greeting/date, which a build-time render would get
wrong. *Solution:* rather than forcing the whole route to
server-render per-request (losing static generation for eight widgets
with no such need), reused the existing `mounted`-guard pattern,
scoped to one small component. *Trade-offs:* this review's own
biggest finding (the missing initial `<h1>`) is the direct cost of
this choice, applied to a heading element specifically — a real,
demonstrated trade-off, not a hypothetical one. *Why chosen:*
minimizing the client-only surface area over the alternative
(route-wide `force-dynamic`). *Why it impresses, even with the defect
found:* the reasoning for *why* a client boundary was needed at all is
sound and well-articulated — the gap is in not fully tracing that
pattern's consequence when the gated content is a page's sole
heading, which is a good, honest teaching example of "a correct
pattern applied to a context its precedent didn't anticipate."

**4. Catching and extracting real (not speculative) duplication
mid-milestone.** *Problem:* `SiteStatus`→badge-color mapping was
written out identically in two widgets. *Solution:* extracted to
`status-meta.ts` only after the second occurrence, not built ahead of
need. *Trade-offs:* none of note — a small, low-risk refactor.
*Why chosen:* reactive extraction matches the project's own stated
"no premature abstraction" standard precisely. *Why it impresses:*
shows the discipline to actually run the self-review pass promised in
the process, and follow through on what it finds, rather than treating
"self-review" as a formality — verified in this report by grep, not
just by trusting the DEVLOG's claim.

**5. Verifying the production build artifact directly, not just dev
mode, when a defect was suspected.** *Problem:* this review's own
audit found a heading-structure defect that seemed timing-dependent.
*Solution:* rather than stop at a dev-server curl check, fetched the
*actual* prerendered static HTML from `.next/server/app/dashboard.html`
— the literal file real users and crawlers receive — and confirmed the
defect there too. *Trade-offs:* extra verification steps.
*Why chosen:* dev-mode SSR and the production static-export pipeline
are not guaranteed identical; the claim "this defect affects real
users" needed the strongest available evidence, not the most
convenient. *Why it impresses:* this is a review methodology decision,
not a Milestone 5 implementation decision — but it's exactly the kind
of "assume nothing, verify everything" instinct that separates a
credible review from an assumed one, and it's what turned a suspected
issue into a confirmed one.

---

## Resume Highlights

ATS-friendly bullet points, based only on work completed in Milestone 5:

- Designed and implemented a mock-service-to-TanStack-Query data
  architecture (service layer, typed domain models, query hooks) for a
  9-widget dashboard, enabling a future REST API to replace mock data
  without component-level changes.
- Built a deterministic failure-injection strategy to reliably
  demonstrate async error/retry UI states, replacing random failure
  simulation with reproducible, testable behavior.
- Implemented a Recharts-based analytics visualization with
  accessible chart labeling (`role="img"`, computed data-driven
  descriptions) and a local time-range filter, integrated with
  TanStack Query caching.
- Evaluated and scoped Zustand global state usage against a
  component-local alternative, applying YAGNI reasoning to reject a
  suggested state pattern that had no actual consumers.
- Authored an architecture decision record and engineering journal
  entries documenting the dashboard's data-fetching, caching, and
  state-management design for future team onboarding.

---

## Future Backlog Recommendations

Reviewing `docs/ENGINEERING_JOURNAL.md`'s "Future Backlog" section
against this report's findings:

- **Promote:** Nothing currently in the backlog needs promotion to
  blocking status — none of this milestone's *carried-over* items
  (mobile search focus, `isActive` matching) were made worse or more
  urgent by Milestone 5.
- **Add — High Priority:** The missing-`<h1>` finding (Technical Debt
  #1). Severity and verified real-world impact (confirmed in the
  actual production artifact) justify High, alongside the existing
  mobile-search-focus item.
- **Add — Medium Priority:** The `aria-disabled`/native-`disabled`
  inconsistency (#2), the dead `getQuickActions()` export (#3), and
  the unwired notification store (#4) — all real, none urgent enough
  to block a release on their own, but all should be fixed before they
  compound (e.g., before another widget copies the `aria-disabled`
  pattern from `WelcomeSection` instead of `QuickActions`).
- **Add — Low Priority:** Missing `data-slot` on 2 widgets (#5), the
  unnecessary `cn()` call (#6).
- **Keep as-is:** The existing Medium item about Recent Drafts' module-
  scoped failure state — still accurate, still acceptable for mock
  data, unaffected by this milestone.
- **Demote:** None.
- **Remove:** None — every existing backlog item is still accurate and
  still open.

---

## Final Verdict

### Is this milestone acceptable?

**YES.** The dashboard is functionally complete, matches the brief's
scope precisely (including its explicit exclusions), and every
verification gate (lint, typecheck, build) passes. The findings in
this report are real but contained — none block the milestone's stated
objective of proving the mock-to-Laravel-ready data architecture.

### Would you approve this Pull Request?

**YES**, with the four Technical Debt items above filed as required
follow-up (not blocking merge, but not to be forgotten — the same
standard Milestone 4's review applied to its own condition). The
architecture underneath is sound enough that approving now and fixing
forward is the right call, consistent with how this project has
handled comparable findings before (Milestone 4.1's mobile-search
focus gap).

### Would you deploy this milestone to production?

**NO — not as-is.** A verified, zero-`<h1>` production HTML payload on
the application's primary landing page is a real, user- and
crawler-facing defect, not a theoretical one. It's also a small,
well-understood, single-file fix. Recommend fixing Technical Debt #1
(and ideally #2–#4, all similarly small) before a production deploy,
not before continuing development.

### Is the project ready for Laravel?

**YES.** Every finding in this report is a presentation-layer or
wiring defect. The mock service layer, typed domain models, and query
hook boundaries are all correctly shaped for a REST API swap —
verified by construction, not asserted: every hook imports exactly one
service function and nothing else.

### Does this require a Milestone 5.1?

**NO.** None of this report's findings are architectural — each is
fixable within a single component/file without touching the
service/hook/type layering. Per this review's own instruction to
reserve a dedicated hardening milestone for architectural issues, these
belong in the backlog (Technical Debt #1 at High priority given its
verified severity, #2–#4 at Medium) rather than triggering a Milestone
5.1 process.

### Overall Grade

**B+**

The data/state architecture, the deterministic retry demonstration,
and the documentation are genuinely strong — comparable to or
exceeding the quality bar Milestones 4 and 4.1 set. What holds this
below an A-range grade is a cluster of four real findings that a
thorough final cross-widget consistency pass should have caught,
most notably a recurrence of a defect *class* (`page-has-heading-one`)
this project already spent real, documented effort fixing once before,
in Milestone 4.1. That recurrence — in new code, via a different
mechanism, on the single most important page in the app — is the
specific reason this isn't rated alongside Milestone 4.1's A-.
It is, however, a meaningfully smaller and more contained set of
issues than Milestone 4's B (a structural landmark defect every future
page would have inherited): every finding here is a one-file fix, none
compound, and the underlying architecture this milestone exists to
prove out — mock data standing in cleanly for a future Laravel API —
is sound and ready to build on.
