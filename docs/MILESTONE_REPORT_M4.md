# Milestone 4 Report

## Date

2026-07-10

---

> **Update — 2026-07-11, Milestone 4.1 (Product Shell Hardening).**
> Every confirmed issue and every recommendation below was addressed
> as a dedicated patch milestone. Summary (full detail in
> `docs/DEVLOG.md`'s Milestone 4.1 entry and
> `docs/ENGINEERING_JOURNAL.md`):
>
> - **Nested `<main>` (Technical Debt #1, Recommendation #1)** — Fixed:
>   `DashboardLayout`'s inner wrapper changed from `<main>` to `<div>`;
>   `not-found.tsx` also given its own `<main>` (it had none at all,
>   sitting outside the route group — a gap this report didn't catch).
>   Verified: exactly one `<main>` per route across all 7 routes.
> - **Stray `.gitkeep` (Technical Debt #2, Recommendation #2)** —
>   Fixed, and a **second instance this report missed**
>   (`src/hooks/.gitkeep`) found and removed by checking every
>   `.gitkeep` in the project against its directory's actual contents,
>   not just the one already flagged.
> - **Audit rule-tag blind spot (Risk #1, Recommendation #3)** — Acted
>   on, not just noted: widened `axe-core` audits to include
>   `best-practice` tags. This immediately surfaced two real violations
>   neither this report nor Milestone 4's own testing had found —
>   `heading-order` and `page-has-heading-one` (`EmptyState`'s
>   hardcoded `<h3>` skipped a level after every `PageHeader`'s `<h1>`,
>   and left pages with no `<h1>` at all where `EmptyState` was the
>   only heading). Fixed via a new `titleAs` prop; re-verified at 0
>   violations across all 7 routes with the same widened scope.
> - **`isActive` exact match (Technical Debt #3, Recommendation #4)** —
>   Evaluated, deliberately left unchanged per the Milestone 4.1
>   brief's own instruction not to over-engineer ahead of a real need.
>   Documented inline and in `docs/adr/0002-product-shell.md`.
> - **Mobile search (Technical Debt #4, Recommendation #5)** — Fixed:
>   inline-expanding search UX, chosen over `Sheet`/`Dialog`/`Popover`
>   (reasoning in the ADR update).
> - **"Help & Support" inert link (Recommendation #6)** — Fixed:
>   `aria-disabled="true"` + `preventDefault()`, both interaction paths
>   (mouse and keyboard) verified independently.
>
> The ratings and grade below reflect the state **at the time this
> report was written** and are left as-is for an accurate record of
> what Milestone 4 shipped — not silently rewritten or upgraded by this
> note. Notably, Milestone 4.1's own independent verification found a
> **new** defect (the heading-hierarchy issue) that this report did not
> catch either — worth remembering when reading the confidence of any
> "0 violations" claim below, including this report's own.

---

## Objective

Build the Product Shell: a reusable navigation and layout system
(sidebar, header, routing, UX states) that every future module sits on
top of, with zero business functionality — every route is a
placeholder built from `PageHeader` + `EmptyState`.

---

## Product Shell Review

**Layout.** `DashboardLayout` composes `SidebarProvider` → `AppSidebar`
+ `SidebarInset` (header + content). Clean, minimal, correctly
delegates to the `sidebar` primitive rather than reimplementing
positioning logic. One real defect found in this review (see
Accessibility Review): `DashboardLayout` renders its own `<main>`
wrapper *inside* `SidebarInset`, which is itself a `<main>` element —
nested `<main>` landmarks, invalid per the HTML Living Standard.

**Navigation.** Genuinely configuration-driven — `src/lib/
navigation.ts` is the single array both `AppSidebar` and the header's
breadcrumb resolver read from. Verified by reading the code, not just
trusting the claim: `AppSidebar` does `navigation.map(...)` with no
hardcoded route names, `getNavTitle()` looks routes up from the same
array. Active-state matching is `pathname === item.href` — exact match
only. This is correct for every route that exists today (all flat,
one level deep) but won't highlight a parent nav item once nested/
detail routes exist (e.g. a future `/content/[id]`) — worth revisiting
before Milestone 5 adds real content routes.

**Header.** Breadcrumbs are real and correctly derived. One
overlooked responsive gap: the search input is `hidden sm:block` —
**fully removed** below the `sm` breakpoint, not condensed to an icon
toggle. Mobile users have no way to search at all, not even a
degraded affordance. Minor today (search is non-functional anyway),
but worth fixing before search becomes real.

**Sidebar.** Built on shadcn's `sidebar` primitive, integrated by hand
to protect Milestone 3.1's hardened `Button`/`Input`/`Skeleton`/
`Tooltip` — confirmed via `git`/file inspection that those four files
are untouched from their Milestone 3.1 state. The "Help & Support"
footer link (`href="#"`) is inert but not marked `aria-disabled` —
minor; a real link with no destination yet is a reasonable stopgap,
but an inert `#` href reads to a keyboard/screen-reader user as if it
does something.

**Routing.** `(app)` route group correctly scopes the shell to exactly
the six intended pages; `not-found.tsx` correctly sits outside it.
Verified `src/app/page.tsx` was removed (not duplicated) when moved
into the group — no route collision.

**Responsiveness.** Verified via the milestone's own extensive
screenshot evidence (desktop/tablet/mobile/ultra-wide, collapse/
drawer states) — content stays `max-w-7xl`-constrained on ultra-wide,
mobile drawer opens/closes correctly. Not independently re-verified at
runtime for this report (see Documentation Review's note on process);
static review of the responsive utility classes is consistent with
the documented screenshots.

**Future scalability.** Adding a module costs one `navigation.ts`
entry + one route folder — verified true by reading the code, not
just asserted. `ProtectedLayout`'s placement means Milestone 8 doesn't
need to touch any existing route.

---

## Accessibility Review

| Area | Rating | Notes |
| --- | --- | --- |
| Keyboard Navigation | ★★★★★ | Tab order follows DOM/visual order; sidebar items, header controls, dropdown/popover content all reachable and operable via keyboard (confirmed in the milestone's own testing and consistent with Base UI's default behavior). |
| Focus Order | ★★★★★ | No `tabIndex` overrides that would scramble order; matches visual layout (sidebar → header → content). |
| ARIA | ★★★☆☆ | Icon-only buttons all have `aria-label` (compile-time enforced — verified by grep, zero gaps). Breadcrumb has `aria-label="breadcrumb"`. But: the nested-`<main>` defect (see below) creates an ambiguous/duplicate landmark structure that undermines otherwise-correct ARIA usage — a screen reader's landmark list would show a confusing structure. |
| Contrast | ★★★★★ | No new color usage this milestone beyond Milestone 3.1's already-corrected tokens; sidebar/header use existing `sidebar-*`/`border`/`muted-foreground` tokens, all previously verified. |
| Responsive usability | ★★★★☆ | Mobile drawer, tablet, ultra-wide all correct per documented evidence. Docked for the mobile search gap (see Product Shell Review) — not a broken experience, but a real usability gap for a fully mobile user. |
| Screen reader friendliness | ★★★☆☆ | Landmarks (`header`, `nav[aria-label="Main"]`, and now a duplicated `main`) are mostly right, but the nested-`main` issue is exactly the kind of defect that reads fine visually and to a mouse user, while actively confusing landmark-based screen reader navigation (e.g. VoiceOver/NVDA's "jump to main content" would have two candidates, nested inside each other). |

**Overall accessibility: Strong, with one real, previously-uncaught
defect.** Every check this project's own `axe-core` audits performed
passed — because those audits were scoped to `wcag2a`/`wcag2aa`/
`wcag21a`/`wcag21aa` tags, and axe's `landmark-one-main` check is
typically tagged `best-practice`, not a strict WCAG success criterion,
so it was **out of scope** for every audit run this milestone. This
is a methodology gap, not a false "0 violations" claim — the audits
did exactly what they were configured to do, but the configuration
had a blind spot. Worth widening the tag scope (or adding
`bestPractice` rules) for future audits.

---

## Performance Review

**Server vs. Client Components** — verified by grep across every new
file, not just trusted: 5 Client Components (`app-header.tsx`,
`app-sidebar.tsx` — both need `usePathname`; `theme-provider.tsx`,
`theme-toggle.tsx` — need `next-themes` hooks; `(app)/error.tsx` — a
Next.js-mandated convention for error boundaries), 10 Server
Components (all six pages, `(app)/layout.tsx`, `(app)/loading.tsx`,
`dashboard-layout.tsx`, `protected-layout.tsx`). This is correct,
minimal-necessary client-boundary usage — matches `CODING_STANDARDS.md`
precisely.

**Bundle impact** — per the milestone's build output (814 B per route,
102 kB shared, unchanged in shape from Milestone 3): the entire
sidebar/header/navigation system added essentially zero measurable
weight to the shared bundle relative to the design-system baseline,
because it's the same primitives already paid for. Not independently
re-run for this report (see note above on review methodology) — the
build output is deterministic and was already captured with full
command transcripts in `DEVLOG.md`.

**Rendering strategy** — every page is static (`○` in the build
output), appropriate for placeholder content with no data dependency
yet. `(app)/loading.tsx` correctly scopes the Suspense boundary so
route transitions only re-render the content region, not the whole
shell (verified by file placement: `loading.tsx` sits at the route
group level, sibling to `layout.tsx`, which is how Next.js scopes it).

**Component reuse** — high. `EmptyState` is reused four distinct ways
this milestone alone (six placeholder pages, the error boundary, the
404 page, and the notifications popover) — a good sign the Milestone 3
`common/` layer is earning its cost. No duplicate "empty state" markup
was hand-rolled anywhere.

**Potential optimizations** — none needed yet; there's no real data
fetching or heavy computation to optimize against. Worth revisiting
once Milestone 5 introduces real dashboard data.

---

## Architecture Review

| Category | Rating | Reasoning |
| --- | --- | --- |
| Current Architecture | ★★★★☆ | Clean separation (`ui/`/`common/`/`layout/`/route group), consistent with prior milestones. Docked one star for the nested-`main` defect — a structural, not cosmetic, architectural mistake in code written this milestone (not inherited from a vendor). |
| Scalability | ★★★★★ | Configuration-driven navigation and route-group scoping both verified true by reading the code. Adding a module is genuinely additive, not a Sidebar/Header edit. |
| Maintainability | ★★★★☆ | Small, focused files (largest hand-written file is `app-header.tsx` at 138 lines); `sidebar.tsx` at 728 lines is vendor-integrated infrastructure, consistent with the exemption already established for `Card`/`Tooltip` in Milestone 3. Docked slightly: the stray `src/components/layout/.gitkeep` (left behind after the folder gained real content, unlike the equivalent cleanup done correctly for `src/components/` and `src/lib/` in the same milestone) is a small but real consistency slip. |
| Performance | ★★★★★ | Correct Server/Client boundary discipline verified by direct inspection, not assumed; zero bundle cost beyond what Milestone 3 already paid for. |
| Accessibility | ★★★☆☆ | Strong fundamentals (keyboard, focus, contrast, ARIA labeling) but one real structural defect (nested `main`) that slipped past every audit run this milestone due to a rule-tag scoping gap, not a testing failure of effort — the process was thorough, the net had a hole in it. |
| Developer Experience | ★★★★★ | `docs/adr/0002-product-shell.md` and `docs/ENGINEERING_JOURNAL.md` document *why*, not just *what* — a future contributor (or this project's own next milestone) has the reasoning, not just the diff. The `--dry-run`-before-`shadcn add` rule is now a documented, generalizable practice, not folklore. |
| Code Organization | ★★★★☆ | Matches `CODING_STANDARDS.md` and the established `ui/`/`common/`/`layout/` split precisely. Same minor docking as Maintainability for the stray `.gitkeep`. |
| Reusability | ★★★★★ | `EmptyState` reused four ways this milestone; `navigation.ts` is a genuine single source of truth, not a partial one. |

---

## UX Review

**Navigation clarity** — high. Grouped sidebar sections ("Manage"),
consistent iconography, active-state highlighting all read clearly in
the documented screenshots.

**Discoverability** — good for what exists today; the "Help & Support"
link pointing nowhere (`href="#"`) is a minor discoverability
inconsistency — it visually promises a destination it doesn't have.

**Consistency** — strong. Every placeholder page follows the identical
`PageHeader` + `EmptyState` pattern; the header's icon-button row
(notifications/theme/user) is visually and behaviorally uniform.

**Responsiveness** — very good on desktop/tablet/ultra-wide; the
mobile search omission (not degraded, entirely absent) is the one real
gap.

**Information hierarchy** — clear: breadcrumb → page title →
description → content region, consistently applied across all six
pages.

**Professional appearance** — matches the stated Vercel/Linear/GitHub
design inspiration well; dark-mode-first, minimal, no decorative
excess, consistent with every prior milestone's visual language.

**Future scalability** — the UX pattern (header + description +
`EmptyState`) will need to be *replaced*, not extended, once real
content lands in Milestone 5 — worth flagging so "placeholder UX" isn't
mistaken for "final UX" by a future reviewer skimming the shipped
pages.

---

## Documentation Review

- **`PROJECT.md`** — Updated, thorough. New "Product Shell" section
  covers routing, navigation model, sidebar, header, auth boundary,
  UX states. Known Limitations section already correctly documents the
  "no skip link" gap; does **not** yet document the mobile-search gap
  or the nested-`main` defect (both new findings from this review —
  reasonable, since they weren't known when `PROJECT.md` was written).
- **`ROADMAP.md`** — Updated, accurate. Retroactively added the 3.1
  entry it was missing, and 4 is correctly checked off with a specific
  summary, not just a checkbox.
- **`DEVLOG.md`** — Updated, detailed, matches what's actually in the
  codebase (verified the "5 client / 10 server component" split and
  the `sidebar`/`sheet` hand-integration claims independently — both
  checked out).
- **`ENGINEERING_JOURNAL.md`** — Created, well-scoped (5 new
  investigations + 2 backfilled), correctly distinct in format from
  `DEVLOG.md` (reasoning-focused, not changelog-focused).
- **ADRs** — `0001-design-system.md` (retroactive) and
  `0002-product-shell.md` both present, follow the requested
  Decision/Context/Alternatives/Chosen Solution/Trade-offs/Future
  Implications structure precisely.

---

## Technical Debt

1. ~~**Nested `<main>` landmarks**~~ **Resolved in Milestone 4.1** —
   `DashboardLayout`'s inner wrapper changed to `<div>`. Also found
   during the fix: `not-found.tsx` had no `<main>` at all (a gap this
   report didn't catch, since it sits outside the route group and this
   report's checks focused on the shell); given its own.
2. ~~**Stray `src/components/layout/.gitkeep`**~~ **Resolved in
   Milestone 4.1** — removed, along with a second instance
   (`src/hooks/.gitkeep`) this report's own review missed.
3. `isActive = pathname === item.href` (exact match) won't highlight a
   parent nav item once nested/detail routes exist under `/content`,
   `/wordpress`, etc. — not a bug today (no such routes exist), but
   will need a "starts with" or route-segment-aware match before
   Milestone 5/9 add them. **Evaluated in Milestone 4.1, deliberately
   left unchanged** per that milestone's own explicit instruction not
   to over-engineer ahead of a real nested route to test against.
4. ~~Mobile search is fully hidden~~ **Resolved in Milestone 4.1** —
   inline-expanding search UX on mobile.
5. Carried over, still open: `src/styles/` (Milestone 1) remains
   empty and undocumented; `components.json`'s `"style": "base-nova"`
   preset-naming coupling (Milestone 2/3 report).
6. **New in Milestone 4.1:** `EmptyState` hardcoded an `<h3>` title,
   which skipped a heading level after every `PageHeader`'s `<h1>` and
   left `not-found.tsx`/`(app)/error.tsx` with no `<h1>` at all. Not
   found by this report — surfaced only once Milestone 4.1 widened the
   `axe-core` audit per Recommendation #3 below. Resolved: `titleAs`
   prop, default `"h2"`, with `"h1"` passed explicitly where
   `EmptyState` is a page's only heading.

---

## Risks

1. ~~**Audit rule-tag scope has a blind spot.**~~ **Acted on in
   Milestone 4.1, not just noted** — audits widened to include
   `best-practice` tags. This is not a risk that turned out to be
   overcautious: doing it immediately surfaced two more real
   violations (`heading-order`, `page-has-heading-one`) neither this
   report nor Milestone 4's testing had found. Now the standing
   practice, not a one-off.
2. No automated regression testing (component or route level) exists
   yet — carried forward from every prior report; Testing is
   Milestone 10. Each new milestone currently re-verifies the whole
   shell manually rather than re-running a fixed suite.
3. `components.json`'s preset-naming coupling (carried over, still
   unverified against future CLI versions).

---

## Recommendations

In no particular order (not implemented at the time of this report —
all six resolved in Milestone 4.1, see the update note at the top):

1. ~~Fix the nested-`<main>` defect~~ **Done.**
2. ~~Remove the stray `src/components/layout/.gitkeep`~~ **Done**
   (plus a second instance this report missed, `src/hooks/.gitkeep`).
3. ~~Widen future `axe-core` audits to include `best-practice`-tagged
   rules~~ **Done** — and it worked: found two new real violations.
4. Revisit `isActive` matching (exact vs. prefix/segment-aware) before
   Milestone 5 or 9 introduces nested/detail routes under the existing
   top-level sections. **Evaluated in Milestone 4.1; deliberately left
   unchanged per that milestone's explicit instruction not to
   over-engineer ahead of a real need** — still the right call to
   revisit once a nested route actually exists.
5. ~~Give mobile users a degraded search affordance~~ **Done** —
   inline-expanding search.
6. ~~Mark the sidebar's "Help & Support" link `aria-disabled="true"`~~
   **Done**, plus a `preventDefault()` for the keyboard-activation path
   `pointer-events: none` alone doesn't cover.

---

## Ready for Milestone 5?

**YES, with one condition.** The shell's architecture, navigation
model, and process discipline are sound — this remains genuinely
strong work. But Milestone 5 will build real dashboard screens
*inside* `DashboardLayout`, meaning every one of those screens
inherits the nested-`<main>` defect the moment it's built on this
foundation. Recommend fixing Technical Debt #1 (or explicitly
accepting it) before or immediately alongside Milestone 5, the same
"foundation-stage debt becomes user-facing the moment it's built on"
pattern already established in the Milestone 3 review.

> **Condition met — Milestone 4.1.** Fixed and re-verified (0
> `<main>`-nesting issues across all 7 routes, widened `axe-core`
> scope, 0 violations). Milestone 5 can build on `DashboardLayout`
> without carrying this condition forward — though see this report's
> update note: the same widened verification also found a *different*
> real defect (heading hierarchy) that neither this report nor
> Milestone 4 itself had caught, a reminder that "condition met" is
> not the same claim as "no further issues exist."

---

## Overall Grade

**B**

The engineering process continues to be genuinely strong: real bugs
were found and fixed through actual interaction testing rather than
trusting static checks (the `DropdownMenuGroup` crash, the
`nativeButton` warning), a risky vendor-file overwrite was caught and
avoided before it destroyed prior hardening work, and the
documentation (ADRs, Engineering Journal) captures reasoning, not just
outcomes. That is A-level engineering discipline.

The grade holds at a **B**, for the same reason as the Milestone 3
review: an independent pass found a real, structural accessibility
defect — nested `<main>` landmarks — sitting in code written this
milestone, which three consecutive "0 violations" `axe-core` runs did
not catch because of a rule-tag scoping gap in the audit
configuration itself. The lesson isn't "the verification was
performed carelessly" — it demonstrably wasn't, this milestone's
testing was more thorough than most software ships with. The lesson
is that "0 violations" is a claim about the *rules that ran*, not a
guarantee of full compliance, and a milestone report has to say that
plainly rather than let a clean audit number stand in for a clean
bill of health.