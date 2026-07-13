# Milestone 3 Report

## Date

2026-07-10

---

> **Update — 2026-07-10, Milestone 3.1 (Design System Hardening).**
> Every finding below was addressed the same day as a dedicated patch
> milestone, not deferred. Summary of resolution (full detail in
> `docs/DEVLOG.md`'s Milestone 3.1 entry):
>
> - **Contrast (Risk #2 / Technical Debt #3, serious axe violation)** —
>   Fixed at the token level in `globals.css`. Re-ran the same axe-core
>   audit used for this report against the corrected values: **0
>   violations** in both light and dark mode (was 2 light-mode
>   violations). Also caught and fixed two additional failures this
>   report's audit hadn't surfaced (`destructive` on its own badge
>   tint, `muted-foreground` on `muted`) via a more exhaustive,
>   independently-built contrast measurement script.
> - **Icon-only button naming (Risk #1, critical axe violation)** —
>   Resolved with compile-time enforcement: `Button`'s props are now a
>   discriminated union requiring `aria-label` whenever `size` is an
>   icon size. Verified both directions (missing `aria-label` fails
>   `tsc`; providing it compiles) with a throwaway type-check file.
> - **`data-slot` inconsistency (Technical Debt #1, #2)** — `Badge`,
>   `StatCard`, and `StatusBadge` all now expose `data-slot`. Confirmed
>   via `grep -L` that every component file has it — zero gaps left.
> - **Badge review** — Decided to keep it as generated (six variants
>   already sufficient), plus the `data-slot` fix above.
>
> The ratings and grade below reflect the state **at the time this
> report was written** and are left as-is for an accurate record of
> what Milestone 3 shipped — they are not silently rewritten or
> upgraded by this note. See `docs/DEVLOG.md`'s Milestone 3.1 entry for
> the full resolution detail.

---

## Objective

Establish WP Studio's design system foundation: a reusable `ui/`
primitive layer, a `common/` composite layer, a typography scale,
iconography conventions, and expanded design tokens — all decoupled
from any page, feature, or business logic.

---

## Completed Tasks

- Scoped the milestone with the user before building: the brief's ~19
  `ui/` + ~9 `common/` examples were "Examples:", not a mandatory
  checklist, and building all of them at once would conflict with the
  project's own "never generate thousands of lines in one response"
  rule. Agreed on a core set now, rest added on demand.
- Verified `npx shadcn add` still resolves the `nova` preset correctly
  (a risk flagged in the Milestone 2 report) before generating anything.
- Generated 9 `ui/` primitives via the shadcn CLI: Button, Input,
  Textarea, Label, Card, Badge, Avatar, Skeleton, Tooltip.
- Hand-built a 10th primitive, `Typography`, implementing the
  requested type scale.
- Wired `TooltipProvider` into `src/app/layout.tsx` (required for
  Tooltip to function — provider wiring, not a Milestone 4 layout
  component).
- Added a `loading` prop to `Button` (spinner + `aria-busy` +
  `disabled`) after self-review found "Loading" state was required but
  missing.
- Hand-built 5 `common/` composites: PageHeader, StatCard, StatusBadge,
  EmptyState, SearchInput. Consolidated the brief's overlapping
  `MetricCard`/`StatCard` examples into one component.
- Created `src/components/layout/` empty, reserved for Milestone 4.
- Expanded design tokens in `globals.css`: `::selection`, a styled
  scrollbar, a previously-missing `destructive-foreground`.
- Documented container-width and hover/disabled-state conventions
  (deliberately not new tokens — Tailwind's existing scale/utilities
  already cover them).
- Documented iconography conventions (Lucide-only, 4 usage categories).
- Visually verified every component in a real browser (light/dark/
  mobile, keyboard focus, hover interaction) via a temporary,
  uncommitted preview page, using Playwright since no project "run"
  skill or `chromium-cli` existed yet in this environment. Found and
  fixed a real bug this way (see Design Decisions). Reverted the
  preview page and confirmed an empty diff before finishing.
- Ran a second, dedicated verification pass for **this** report: an
  automated `axe-core` accessibility audit (see Accessibility Review)
  — again via a temporary preview page, reverted afterward.
- Updated `PROJECT.md`, `ROADMAP.md`, `DEVLOG.md`.

---

## Files Created

```
docs/MILESTONE_REPORT_M2.md          (from the prior review, not yet committed)
src/components/ui/button.tsx
src/components/ui/input.tsx
src/components/ui/textarea.tsx
src/components/ui/label.tsx
src/components/ui/card.tsx
src/components/ui/badge.tsx
src/components/ui/avatar.tsx
src/components/ui/skeleton.tsx
src/components/ui/tooltip.tsx
src/components/ui/typography.tsx
src/components/common/page-header.tsx
src/components/common/stat-card.tsx
src/components/common/status-badge.tsx
src/components/common/empty-state.tsx
src/components/common/search-input.tsx
src/components/layout/.gitkeep
components.json                       (created by shadcn CLI in Milestone 2, unchanged this milestone)
```

---

## Files Modified

```
src/app/globals.css     (design tokens: selection, scrollbar, destructive-foreground)
src/app/layout.tsx      (TooltipProvider wiring)
docs/PROJECT.md
docs/ROADMAP.md
docs/DEVLOG.md
```

`src/components/.gitkeep` was deleted — the directory now has real
subdirectories and no longer needs a placeholder.

None of this milestone's work is committed yet (working tree is ahead
of `HEAD` at commit `027740d`).

---

## Components Added

**`ui/` primitives**

| Component | Purpose |
| --- | --- |
| `Button` | Primary interactive action element. Variants: default, outline, secondary, ghost, destructive, link. Sizes: xs–lg + icon sizes. Supports `loading`. |
| `Input` | Single-line text entry. |
| `Textarea` | Multi-line text entry. |
| `Label` | Accessible form field label, pairs with Input/Textarea via `htmlFor`. |
| `Card` (+ Header/Title/Description/Action/Content/Footer) | Generic content container — the base every `common/` composite in this milestone builds on. |
| `Badge` | Small status/label pill. Variants: default, secondary, destructive, outline, ghost, link. |
| `Avatar` (+ Image/Fallback/Group/GroupCount/Badge) | User/site identity representation with image-load fallback. |
| `Skeleton` | Loading placeholder (pulsing block), pairs with any component while data is in flight. |
| `Tooltip` (+ Trigger/Content/Provider) | Contextual hint on hover/focus — most needed for icon-only buttons. |
| `Typography` | Single polymorphic text component implementing the full type scale (`display`, `h1`–`h4`, `body`, `body-sm`, `caption`, `label`, `code`). |

**`common/` composites**

| Component | Purpose |
| --- | --- |
| `PageHeader` | Page-level title + description + right-aligned actions slot. |
| `StatCard` | Metric display (value + label + optional icon + optional up/down/neutral trend). Consolidates the brief's separate `MetricCard`/`StatCard` examples. |
| `StatusBadge` | Semantic status pill (`success`/`warning`/`error`/`neutral`) — deliberately generic, not domain-specific, so features map their own vocabulary onto it. |
| `EmptyState` | "Nothing here yet" placeholder: icon + title + description + optional action. |
| `SearchInput` | Input + search icon + conditional clear button, controlled. |

---

## Design Decisions

- **Base UI over Radix**, matching the shadcn CLI's own current default
  rather than overriding to the older, more commonly-known Radix path.
- **Did not import the CLI's bundled `shadcn/tailwind.css`** — it mixes
  needed infrastructure (data-state variants, accordion keyframes) with
  decorative shimmer/scroll-fade utilities that contradict the "no
  unnecessary animations" brief. Inlined only what's needed.
- **`StatusBadge` uses a generic 4-state vocabulary**
  (`success`/`warning`/`error`/`neutral`), not feature-specific terms —
  keeping `common/` free of business logic per the milestone's explicit
  boundary.
- **Real bug found via browser verification, not tooling**: composed
  `Tooltip` + `Button` using Radix's `asChild` convention. This Base
  UI-flavored shadcn setup doesn't support `asChild` — it uses a
  `render` prop that accepts a `ReactElement` and merges props/ref into
  it. The mistake produced an invalid nested `<button>` and a React
  hydration error that `tsc`, ESLint, and `next build` all passed
  without complaint — only caught by actually rendering the page.
  Fixed via `render={<Button ... />}`. This is now the documented
  pattern in `PROJECT.md`.
- **Global `:focus-visible` fallback vs. component-level focus rings**:
  clarified (not changed) that every shadcn-generated primitive sets
  `outline-none` and defines its own `focus-visible:ring-3` box-shadow
  ring, which correctly wins over the Milestone 2 global fallback rule
  because Tailwind's utilities layer always beats `@layer base`
  regardless of source order. Documented so it doesn't read as broken
  to a future contributor inspecting `globals.css` in isolation.

---

## Accessibility Review

> Both findings below (icon-only naming, contrast) were resolved in
> Milestone 3.1 — re-running this exact audit against the fixes
> returned 0 violations in both modes. Left unedited below as the
> original findings; see the update note at the top of this report.

**Method**: not just visual inspection. Ran an automated `axe-core`
audit (WCAG 2.0/2.1 A + AA rulesets) against every new component,
rendered in a real browser, in both light and dark mode, via Playwright
— the same temporary-preview-and-revert protocol used for the
Milestone 3 visual verification, run again specifically to produce
evidence for this report.

**Keyboard navigation**: Solid. Tab order reaches every interactive
element in document order; Tooltip opens on keyboard focus as well as
mouse hover (Base UI default behavior), not just on hover — correct
per WCAG 2.1.1.

**Focus states**: Solid, confirmed via computed styles, not just
visual impression. Every interactive primitive shows a visible focus
ring via its own `focus-visible:ring-3 ring-ring/50` box-shadow.

**ARIA support**: Mostly solid — Base UI primitives ship correct roles
and states by default (confirmed: 21 axe rules passed in both light and
dark mode, covering landmarks, roles, and ARIA attribute validity).
**One critical gap found**: an icon-only button (Bell icon inside a
`Tooltip`, no visible text) had no accessible name — axe flagged
`button-name` as a **critical** violation. A `Tooltip` is not a
substitute for an accessible name; screen readers need `aria-label`,
`aria-labelledby`, or visible text, none of which a tooltip provides
automatically. **Important nuance**: this pattern only existed in the
temporary preview page (deleted), so there is no icon-only button
currently in committed code — but nothing in the `Button` component's
API currently *prevents* this mistake (no required `aria-label` on
icon sizes, no lint rule catching it), and Milestone 4 will build
exactly this pattern (a header notification/settings icon button). This
is a validated risk to close before or during Milestone 4, not a
currently-shipped bug.

**Contrast**: **Real, current defect** — unlike the button-name issue,
this one lives in already-committed token/component code. axe flagged
`color-contrast` as a **serious** violation in light mode (0 violations
in dark mode) for the `success` and `warning` semantic tokens used by
`StatusBadge` and `StatCard`'s trend indicator:

| Usage | Measured ratio | WCAG AA requirement |
| --- | --- | --- |
| `text-success` trend text on card background | 3.73:1 | 4.5:1 |
| `text-success` on `bg-success/10` badge fill | 3.32:1 | 4.5:1 |
| `text-warning` on `bg-warning/10` badge fill | 2.10:1 | 4.5:1 |

This confirms a risk the Milestone 3 devlog itself predicted but hadn't
measured ("not yet visually verified against real components"). The
`warning` token in particular fails badly (2.1:1 is less than half the
required ratio) and needs a darker/more saturated value for light-mode
text use before these components see real usage.

**Dark mode**: Solid — 0 contrast violations in dark mode; the same
`success`/`warning` OKLCH values that fail in light mode happen to pass
against the dark background.

**Overall accessibility rating: Good foundation, not yet AA-compliant.**
Structural accessibility (keyboard, focus, ARIA roles) is strong and
verified, not assumed. But there are two concrete issues — one present
defect (contrast) and one validated risk with no current guardrail
(icon-button naming) — that must be resolved before these components
are used in a real, accessible-by-default page.

---

## Performance Review

**Bundle impact**: Currently zero. No page or layout imports any `ui/`
or `common/` component except `TooltipProvider` in the root layout
(required for Tooltip to function). Production build output is
unchanged from Milestone 2 (103 kB First Load JS shared, single static
route). This is expected and correct for a foundation milestone — the
components exist but aren't consumed yet.

**Component complexity**: Low. Largest file is `avatar.tsx` at 109
lines; every file is a focused, single-responsibility unit. No
component exceeds a handful of conditional branches.

**Rendering efficiency**: 11 of 15 files are Server Components by
default (no `"use client"`); only `Avatar`, `Label`, `Tooltip`, and
`SearchInput` opt into the client boundary, each for a concrete reason
(Base UI internal state, interactive event handlers). This matches
`CODING_STANDARDS.md`'s "prefer Server Components" rule precisely —
nothing is client-rendered without a reason.

**Tree-shaking friendliness**: Good. Every component uses named
exports (no default exports, no barrel `index.ts` re-exporting
everything), so consumers only pull in what they import. Lucide React
icons are imported individually per file, not as a namespace import.

**Potential optimizations**: None needed yet — there's no real usage to
optimize against. Worth revisiting once Milestone 4/5 actually import
these components and the bundle has real weight to measure.

---

## Architecture Review

| Category | Rating | Reasoning |
| --- | --- | --- |
| Current Architecture | ★★★★☆ | Clean three-way `ui/`/`common/`/`layout/` split, consistent with the feature-first project structure. Docked for two small, self-introduced `data-slot` inconsistencies (see Naming/Consistency below). |
| Scalability | ★★★★☆ | The "core set now, add on demand" approach scales well and avoids speculative code; `common/` correctly stays generic. Not five stars only because nothing has been "load-tested" by real feature consumption yet. |
| Maintainability | ★★★★☆ | Small, focused files, consistent patterns, thorough docs. Docked for the same minor consistency gaps as above. |
| Performance | ★★★★★ | Zero current bundle cost, Server-Component-first, tree-shake-friendly exports, no unnecessary client boundaries. |
| Accessibility | ★★★☆☆ | Structurally strong (verified via automated audit, not assumed) but has one real contrast defect and one validated naming risk — can't rate higher while concrete WCAG AA failures exist in committed code. |
| Reusability | ★★★★★ | Every component is generic and composable; `StatusBadge`'s abstract status vocabulary is exactly the right shape for reuse across future dashboard/WordPress/content features. |
| Developer Experience | ★★★★☆ | Strong docs and conventions (iconography, typography scale, Tailwind IntelliSense already wired in `.vscode/settings.json`). Docked slightly: Base UI's `render`-prop composition pattern (vs. the more widely-known Radix `asChild`) is a real gotcha that already caused one bug during this milestone. |
| Code Organization | ★★★★★ | Matches `CODING_STANDARDS.md` precisely: feature-first respected, consistent file naming, no premature abstraction, no dead/unused exports beyond the expected "not consumed yet" state. |

---

## Risks

1. ~~**Icon-only buttons have no naming guardrail.**~~ **Resolved in
   Milestone 3.1** — `aria-label` is now a compile-time-required prop
   whenever `Button`'s `size` is an icon size.
2. ~~**`success`/`warning` token contrast is currently below WCAG AA in
   light mode**~~ **Resolved in Milestone 3.1** (see the update note
   at the top of this report).
3. `components.json`'s `"style": "base-nova"` still ties `shadcn add`
   to a specific external registry preset (carried over from the
   Milestone 2 report; unchanged, still low-probability but unverified
   for CLI versions beyond the current one). Not addressed — no new
   `shadcn add` calls were made this milestone to re-verify against.
4. No automated accessibility or visual-regression testing exists in
   CI yet (Testing is Milestone 10) — the axe-core audit for this
   report, and its Milestone 3.1 re-run, were both manual, not a
   repeatable CI gate. Still an open risk, even though the manual
   process has now caught real issues twice.

---

## Technical Debt

1. ~~**`StatCard` and `StatusBadge` don't follow the `data-slot`
   convention**~~ **Resolved in Milestone 3.1** — both now set
   `data-slot`, confirmed via a full `grep -L` sweep of every
   component file.
2. ~~**`Badge` doesn't expose a `data-slot="badge"` DOM attribute**~~
   **Resolved in Milestone 3.1** — added via `mergeProps` (external
   props take precedence over the internal default, confirmed from its
   source).
3. ~~`success`/`warning` OKLCH values need retuning for light-mode text
   contrast~~ **Resolved in Milestone 3.1** — `success`, `warning`,
   `destructive`, and `muted-foreground` all corrected at the token
   level; re-audited with 0 violations.
4. `src/styles/` (from Milestone 1) remains empty and undocumented —
   carried over from the Milestone 2 report, still unresolved. Out of
   scope for Milestone 3.1 (not a Design System accessibility/
   consistency item).

---

## Unused Code

By design, not by accident: every `ui/` and `common/` component
created this milestone is currently unimported by any page or layout
(except `TooltipProvider`, which is required infrastructure). This is
expected for a foundation milestone — Milestone 4/5 are where these get
consumed — but it means 14 of 15 new files are, strictly speaking, dead
code from a static-analysis standpoint today. ESLint doesn't flag this
because they're intentionally-exported library-style modules, not
unused local variables.

---

## Duplicate Logic

None found. `cn()` has a single definition (`src/lib/utils.ts`), no
component reimplements class-merging logic, and `StatusBadge` correctly
composes `Badge` rather than re-implementing badge styling from
scratch. Repeated Tailwind utility strings (e.g. `flex items-center
gap-*`) across files are normal utility-CSS usage, not duplicated
business logic.

---

## Recommendations

In no particular order (not implemented — reporting only):

1. Fix the `success`/`warning` token contrast for light-mode text use
   before Milestone 4/5 build real components on top of them.
2. Add an explicit `aria-label` requirement (via prop typing, a
   dedicated `IconButton` wrapper, or a documented convention) before
   Milestone 4 builds the first icon-only header button.
3. Align `StatCard`/`StatusBadge` with the `data-slot` convention used
   elsewhere.
4. Consider whether `Badge`'s lack of a `data-slot` DOM attribute
   (relative to every other primitive) is worth a follow-up, given it's
   inherited from upstream CLI code rather than something this project
   controls directly.
5. Once Milestone 10 (Testing) exists, fold the axe-core check used for
   this report into a repeatable CI gate rather than a manual,
   report-time-only audit.
6. Resolve `src/styles/`'s undefined purpose (carried over from
   Milestone 2).

---

## Lessons Learned

- **Static checks (`tsc`, ESLint, `next build`) did not catch either of
  this report's real findings** — the earlier `asChild` hydration bug,
  nor this report's contrast/naming issues. All three needed the
  component actually rendered and, for accessibility specifically,
  needed an automated audit tool rather than visual inspection alone.
  Screenshots proved layout and color correctness; they did not prove
  WCAG compliance.
- **A design system's own stated priority ("Accessibility must always
  take priority") is only as good as the verification behind it.**
  Milestone 3's original visual verification was thorough for
  rendering correctness but accessibility was assessed by inspection,
  not measurement — this report's dedicated axe-core pass is what
  actually surfaced the two real issues above.
- **Some technical debt is only debt once something depends on it.**
  The `success`/`warning` contrast problem existed the moment the
  tokens were defined in Milestone 2, but it only became *measurable,
  actionable* debt once Milestone 3 built components that render text
  in those colors — a reminder to re-verify token decisions when they
  first get real usage, not just when they're first defined.

---

## Ready for Milestone 4?

**YES, with one condition.** The architecture, conventions, and process
are sound, and all standard checks (`typecheck`, `lint`, `format:check`,
`build`) pass. However, Milestone 4 will very likely build an icon-only
header button and will very likely use `StatusBadge`/`StatCard` in a
real, visible page — at which point both findings in this report stop
being "foundation-stage debt" and become user-facing accessibility
failures. Recommend addressing Risks #1 and #2 (or explicitly
accepting them and scheduling the fix) before or immediately alongside
Milestone 4, rather than deferring further.

> **Condition met — Milestone 3.1.** Both Risk #1 and Risk #2 were
> resolved the same day, verified with a re-run of the same audit tool
> (0 violations, was 2) and a compile-time enforcement test. Milestone
> 4 can proceed without carrying this condition forward.

---

## Overall Grade

**B**

The engineering process this milestone is genuinely strong: the scope
was negotiated rather than assumed, a real bug was caught and fixed
through actual browser verification rather than trusting static checks,
a missing "Loading" state was caught through deliberate self-review,
and this report's own accessibility audit was run with real tooling
rather than restated visual impressions. That process is A-level work.

The grade is held to a **B** because the milestone's own stated
priority is "Accessibility must always take priority," and this report
found a real, measured WCAG AA contrast failure sitting in already-
committed, reusable component code — not a hypothetical or a process
gap, but a concrete defect that would ship to real users the moment
`StatusBadge` or `StatCard` render success/warning text in light mode.
A design system can't claim accessibility-first and grade an A while
carrying a known, measured accessibility defect into the next
milestone. The fix is straightforward (retune two color values) and
the process that found it should be repeated — but the defect is real
today, and the grade reflects that honestly.