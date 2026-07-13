# Milestone 4.1 Report

## Date

2026-07-11

---

## Objective

Harden the Product Shell before Dashboard work begins: fix the
Milestone 4 report's confirmed issues (landmark nesting, mobile
search, navigation architecture, project cleanup) without adding any
new product functionality.

---

## Issues From M4

| Issue | Status | Why |
| --- | --- | --- |
| Nested `<main>` landmarks | **Resolved** | `DashboardLayout`'s inner wrapper changed `<main>` → `<div>`; confirmed by direct code read and independently re-verified at runtime (`main` count = 1 on all 7 routes, this review's own check, not reused from the milestone's). A related gap the M4 report itself never caught — `not-found.tsx` had **no** `<main>` at all — was also found and fixed. |
| Stray `.gitkeep` files | **Resolved** | `src/components/layout/.gitkeep` removed as flagged, plus `src/hooks/.gitkeep` — a second instance the M4 report missed, found by checking every `.gitkeep` in the project rather than just the one already named. Verified via `git status`: neither path exists in the working tree. |
| `isActive` exact-match navigation | **Not Resolved — by explicit design, not oversight** | The Milestone 4.1 brief itself instructed: evaluate, and leave unchanged if a fix would add complexity without a real route to test against. Correctly followed: the code is unchanged, a clear comment now sits at the call site, and the reasoning is in `docs/adr/0002-product-shell.md`. This is the right call, not a gap — but it is still open, and the report should say so plainly rather than count "documented" as "resolved." |
| Mobile search fully hidden | **Partially Resolved** | The stated problem (mobile users had zero search UI) is genuinely fixed — an icon-triggered inline-expanding search now exists, verified working (autofocus lands on the input, closes cleanly, no console errors). But this review's own interaction testing found the fix introduced a **new**, unaddressed accessibility gap: closing the expanded search does not return focus to the search toggle button — focus falls back to `<body>` (verified via `document.activeElement` after the close click). The original problem is solved; a smaller one was introduced by the solution. See Accessibility Review and Technical Debt below. |
| "Help & Support" inert link | **Resolved** | `aria-disabled="true"` (reusing the existing variant's built-in dimmed/`pointer-events-none` styling) plus an `onClick` `preventDefault()`. Independently re-verified both interaction paths: computed `pointer-events: none` blocks mouse clicks, and a focused-then-`Enter`-pressed test confirms the URL doesn't change via keyboard either. |
| Audit rule-tag scope blind spot (a **risk**, not an issue, in the M4 report) | **Resolved as a process change** | Audits now include `best-practice` tags, not just strict WCAG ones. Independently re-run for this review (all 7 routes + a mobile viewport): 0 violations under the widened scope, confirming the codebase is actually clean under the stricter standard, not just that the milestone claimed it was. |

---

## Product Shell Review

**Layout.** `DashboardLayout` is now landmark-correct: exactly one
`<main>` (from `SidebarInset`), the content wrapper is a plain `<div>`.
Clean, minimal diff — verified via diff-free before/after screenshot
comparison in the milestone's own testing, and re-confirmed structurally
in this review.

**Sidebar.** Unchanged from Milestone 4 except the "Help & Support"
fix. Still built on the vendor `sidebar` primitive; still correctly
config-driven.

**Header.** The one file with real new complexity this milestone.
Reads cleanly — a single `mobileSearchOpen` boolean gates two mutually
exclusive JSX branches within one `<header>` wrapper (avoiding
duplicated header-shell classNames, a small but real DRY win over the
naive two-`<header>`-blocks approach). The gap: no focus-return
handling on close (see Accessibility Review).

**Navigation.** Config-driven model unchanged and still correct by
construction. `isActive` limitation now explicitly documented at the
call site — a future maintainer adding a nested route will see the
comment before writing code, not discover the bug afterward.

**Breadcrumbs.** Unchanged, still correctly derived from `pathname` via
the shared nav config.

**Responsive design.** Verified via the milestone's screenshots
(mobile search open/closed/typed states) — visually correct, no layout
shift. This review did not independently re-screenshot every
breakpoint (static review + the one dynamic behavior that needed
verifying), consistent with using runtime verification only where a
finding required it.

**Configuration-driven navigation.** Still holds — verified again by
reading `navigation.ts` and `AppSidebar` fresh; no hardcoded route
names introduced by this milestone's changes.

**Accessibility.** See dedicated section below — this is where the
milestone's real strengths and its one remaining gap both live.

**Future scalability.** No regressions. The `titleAs` prop on
`EmptyState` is itself a small scalability win: any future page that
needs `EmptyState` as its sole heading (not just the two cases fixed
here) has a documented, explicit way to do it correctly instead of
needing another audit to discover the same bug again.

---

## Accessibility Review

| Area | Rating | Notes |
| --- | --- | --- |
| Semantic HTML | ★★★★★ | Landmark nesting fixed; heading hierarchy fixed and verified correct (`h1 → h2`, no skips) across all 7 routes via a direct heading-tag dump, not just an absence of audit violations. |
| Landmarks | ★★★★★ | Exactly one `<main>` per route, independently re-verified. `<nav>` count of 2 on shell pages (sidebar `nav[aria-label="Main"]` + breadcrumb `nav[aria-label="breadcrumb"]`) is correct, not a defect — two distinct, distinctly-labeled navigation regions. |
| Keyboard Navigation | ★★★★☆ | Sidebar, header controls, dropdown/popover content all keyboard-operable. Docked one star for the mobile search focus-loss gap — a keyboard user closing search loses their position on the page entirely. |
| Focus Order | ★★★★☆ | Correct within each of the header's two states (normal vs. expanded-search), but the *transition between* states doesn't manage focus, which is a focus-order gap in the dynamic sense even though static tab order within either state is fine. |
| Focus Visibility | ★★★★★ | No changes to focus-ring styling this milestone; Milestone 2/3's `focus-visible` treatment still applies uniformly, confirmed present on the new search/close buttons (same `Button` component, same styling). |
| ARIA | ★★★★★ | `aria-disabled` correctly applied and independently confirmed to block both mouse (`pointer-events`) and keyboard (`preventDefault`) activation — a genuinely more rigorous fix than just adding the attribute and assuming it's sufficient. |
| Contrast | ★★★★★ | No new colors introduced; reuses already-corrected Milestone 3.1 tokens throughout. |
| Screen Reader Experience | ★★★☆☆ | Landmarks and headings — the two things a screen reader user relies on most for orientation — are now both correct, which is the milestone's real achievement. The focus-loss gap specifically hurts screen reader users disproportionately (sighted keyboard users can often re-orient visually faster than a screen reader user can re-navigate from a lost focus position), which is why this doesn't rate higher despite the landmark/heading wins. |

**Overall: genuinely stronger than Milestone 4, with one real
regression introduced by the mobile search fix itself.** The pattern
recurring across this project's reports continues here: `axe-core`
(even widened) reported 0 violations in this review's own independent
run, and it still could not have caught the focus-loss issue —
that class of defect is inherently dynamic/interaction-based, outside
what a DOM-snapshot tool checks. Worth naming plainly rather than
letting a clean audit number stand in for a complete one, the same
lesson this project's own reports have now made twice.

---

## Performance Review

**Server Components.** No regressions — the same 10 Server / 5 Client
split from the M4 report holds; this milestone's changes were all
within already-client files (`AppHeader`, `AppSidebar`) or files that
were already correctly server-rendered (`EmptyState`, page components).

**Client Components.** `AppHeader` gained one `useState<boolean>` —
negligible. No new "use client" boundaries were introduced.

**Hydration.** No SSR/hydration-sensitive changes this milestone (the
Milestone 4 `next-themes` flash-prevention work is untouched).

**Rendering.** Unaffected — all routes remain statically rendered.

**Bundle impact.** Two new Lucide icons (`Search`, `X`) added to
`AppHeader`'s imports; confirmed the production build output is
unchanged in shape (814 B/route, 102 kB shared) — Lucide's per-icon
tree-shaking means two additional icons used elsewhere in the app
already (Lucide icons are already a project-wide dependency) add
effectively nothing measurable.

**Potential optimizations.** None warranted — nothing in this
milestone's scope touches data fetching or heavy computation.

---

## Architecture Review

| Category | Rating | Reasoning |
| --- | --- | --- |
| Architecture | ★★★★★ | The nested-`<main>` fix is exactly the "smaller, more correct diff" the Engineering Journal claims — verified by reading it, not just trusting the description. Landmark structure across the whole shell is now correct by construction. |
| Maintainability | ★★★★★ | Both stray `.gitkeep` files gone (including the one the M4 report missed); `isActive`'s limitation is now a comment at the exact line a future maintainer would touch, not buried in a doc they'd have to go find. |
| Scalability | ★★★★★ | `EmptyState`'s `titleAs` prop is a real scalability improvement, not just a bug fix — any future page needing `EmptyState` as a sole heading now has a documented, correct path instead of an implicit trap. |
| Accessibility | ★★★★☆ | Landmark and heading-hierarchy defects — genuinely structural, previously-shipped bugs — are fixed and independently re-verified. Docked one star, not because the fixes are wrong, but because the fix for mobile search introduced a new, real, unaddressed focus-management gap that wasn't caught before this review. |
| Performance | ★★★★★ | Zero measurable cost for meaningfully more functionality (mobile search) and correctness (landmarks, headings). |
| Developer Experience | ★★★★★ | The ADR update explains the mobile-search alternatives with real reasoning, not just a conclusion; the Engineering Journal's Interview Highlights are written well enough to actually be interview-ready (see dedicated review below) — with one gap worth naming there too. |
| Reusability | ★★★★★ | `titleAs` is a clean, minimal, backward-compatible prop addition — every existing `EmptyState` call site needed zero changes except the two that needed the new behavior. |
| Code Organization | ★★★★★ | No structural changes this milestone; the existing `ui/`/`common/`/`layout/` organization absorbed every fix without needing new files or folders. |

---

## Technical Debt

1. **New, found by this review: mobile search doesn't return focus to
   its trigger on close.** Verified empirically —
   `document.activeElement` after closing is `<body>`, not the
   "Search" toggle button. For contrast, Base UI's `Popover`
   (notifications) *does* correctly return focus to its trigger on
   `Escape` — confirmed side-by-side in this review — which makes the
   gap more notable: it's specifically a cost of the hand-rolled
   inline-expand pattern chosen over a primitive-based one
   (`Sheet`/`Dialog`/`Popover` all handle this automatically), not an
   inherent limitation of solving mobile search at all. Straightforward
   fix: capture a ref to the "Search" button and call `.focus()` in the
   close handler.
2. Carried over, unresolved by design (see Issues From M4):
   `isActive` exact-match navigation matching.
3. Carried over, still open: `src/styles/` (Milestone 1) remains empty
   and undocumented; `components.json`'s `"style": "base-nova"`
   preset-naming coupling (Milestone 2/3 report).

---

## Risks

1. **Hand-rolled interactive patterns don't get accessibility behavior
   "for free" the way primitive-based ones do.** This milestone chose
   inline-expand for mobile search specifically to avoid the overhead
   of `Sheet`/`Dialog` — a reasonable call for the reasons documented —
   but the focus-management gap is the concrete cost of that choice
   surfacing already. Worth keeping in mind for future "simplest tool
   for the job" component decisions: the simpler tool sometimes means
   accessibility behavior has to be added by hand rather than inherited.
2. No automated regression testing exists yet (carried forward from
   every prior report; Testing is Milestone 10) — each milestone's
   hardening work is still manually re-verified rather than checked
   against a fixed suite, so a future refactor could silently
   reintroduce a fixed defect (nested `main`, wrong heading level,
   lost focus) without anything catching it until the next manual
   review.
3. `components.json`'s preset-naming coupling (carried over, still
   unverified against future CLI versions).

---

## Interview Highlights Review

Reviewed all five items in `docs/ENGINEERING_JOURNAL.md`'s Interview
Highlights against the requested structure (Problem / Decision /
Trade-offs / Reasoning):

- **Items 1, 2, 3, 5** all explicitly cover all four components
  clearly and concretely — genuinely interview-ready as written, with
  specific verification methods named (not just conclusions asserted).
- **Item 4** ("`axe-core`'s rule-tag scope...") is missing an explicit
  **Trade-offs** callout — it has Problem, Decision ("Chosen
  solution"), an extra "Outcome" note, and Reasoning ("Why this
  matters"), but never names what widening the audit scope actually
  costs. **Suggested improvement:** add a Trade-offs line naming the
  real cost — a wider rule set surfaces more `best-practice`-tagged
  findings that are lower-confidence/more subjective than strict WCAG
  violations, meaning each future audit run needs more human judgment
  to triage (not everything a `best-practice` rule flags is worth
  fixing), rather than the clean pass/fail signal a narrower scope
  gives.
- **Item 2**'s stated Trade-off ("less 'dedicated screen' polish than
  a `Sheet` would give") is real but, in light of this review's
  finding, incomplete — it names an aesthetic cost while missing the
  more consequential one: choosing inline-expand over a `Sheet`/`Dialog`
  is *also* why mobile search doesn't get automatic focus-return on
  close, which a primitive-based alternative would have handled without
  extra code. **Suggested improvement:** update item 2's Trade-offs to
  name this explicitly, ideally once Technical Debt #1 above is fixed,
  so the entry reads as "here's the cost, and here's how it was
  addressed" rather than needing a second pass.

---

## Recommendations

Before Milestone 5 (not implemented — reporting only):

1. Fix the mobile search focus-return gap: capture the "Search" trigger
   button in a ref, call `.focus()` on it when `mobileSearchOpen`
   becomes `false`.
2. Update Interview Highlights item 4 with an explicit Trade-offs line
   (see Interview Highlights Review).
3. Update Interview Highlights item 2's Trade-offs to name the focus-
   management cost once #1 above is fixed.
4. Consider whether other hand-rolled interactive UI (present or
   future) should be audited specifically for "does closing this return
   focus to where it started" — this review found one instance by
   testing for it deliberately, not by accident; it's a class of defect
   worth checking for systematically rather than one component at a
   time.

---

## Ready For Milestone 5?

**YES.** Unlike the Milestone 4 review, this review's finding
(mobile-search focus loss) does not block Milestone 5 the way the
nested-`<main>` defect did — that was a structural defect every future
page would inherit by construction; this is a narrow, contained gap in
one interactive control that doesn't compound as more pages are added
on top of `DashboardLayout`. Reasonable to fix alongside early
Milestone 5 work rather than as a blocking prerequisite, but it should
not be forgotten — Recommendation #1 above is the concrete next step.

---

## Overall Grade

**A-**

This milestone did what a hardening milestone should: it fixed every
confirmed issue from the prior report (verified independently, not
just trusted), found and fixed a completely new class of defect
(heading hierarchy) by actually following through on its own
predecessor's risk finding rather than leaving it as a sentence, and
produced documentation (ADR update, Engineering Journal, Interview
Highlights) that holds up under a critical read.

Short of a full **A**, for two reasons, both genuinely minor: this
review found a real, if narrow, accessibility regression (focus loss
on mobile search close) that the milestone's own testing didn't catch
— its own verification checked the *open* direction (autofocus) but
not the *close* direction, an asymmetry worth noticing for next time.
And the Interview Highlights section, while strong, has one entry
missing an explicit Trade-offs callout and one entry whose stated
trade-off wasn't the most important one. Neither issue is severe, both
are cheap to fix, and — notably — neither was hidden or glossed over
in this milestone's own documentation; they were simply not found yet.
That's a meaningfully better position than Milestone 4 was in, which is
what makes this an A- rather than a repeat B.