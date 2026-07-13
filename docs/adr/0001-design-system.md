# 0001 — Design System Foundation

**Status:** Accepted (retroactive — captures decisions made across
Milestones 2, 3, and 3.1)

## Decision

Build the design system on the shadcn CLI's current generation (v4.x):
Base UI as the primitive/accessibility layer, Tailwind CSS v4's
CSS-first theming (no `tailwind.config.ts`), a deliberately trimmed
core set of `ui/` primitives expanded on demand, and compile-time
(not just convention-based) accessibility guarantees where practical.

## Context

WP Studio needed a reusable component foundation before any feature
work could start. Three real constraints shaped the decision more than
initial assumptions did:

- The shadcn CLI's current major version is a substantial rework of
  what's commonly documented/known — registry-based presets, a
  `base`/`radix` primitive-library choice, and (as discovered in
  Milestone 4) an `add` command that will silently overwrite local
  files sharing a registry path, with no awareness of local
  customization.
- The project's own brief repeatedly emphasizes "accessibility must
  always take priority" and "never generate thousands of lines in one
  response" — two constraints in tension with blindly generating the
  full example component catalog upfront.
- Early milestones (2 and 3) shipped real defects — a self-referencing
  CSS variable, WCAG AA contrast failures, a missing accessible name on
  icon-only buttons — that static checks (`tsc`, ESLint, `next build`)
  did not catch. Only actually rendering components in a browser and
  running automated accessibility tooling surfaced them.

## Alternatives Considered

**Primitive library — Base UI vs. Radix UI.** Radix is the
longer-established, more widely documented choice and what most
existing shadcn/ui tutorials assume. Base UI is newer, built by the
same team (post-Radix, in collaboration with MUI), and is the shadcn
CLI's own current default. Chose Base UI: using the tool's own default
is the more accurate reading of "latest stable," and the team's
lineage/credibility offset the documentation-maturity gap.

**Component scope — full catalog vs. core set.** The Milestone 3 brief
listed ~19 `ui/` primitives and ~9 `common/` components as "Examples."
Building all of them immediately would have been more complete
upfront, but directly conflicts with the project's own iterative-scope
philosophy and risks shipping untested, unused code. Chose a trimmed
core set (asked the user to confirm before proceeding), with the
explicit rule that further primitives get added when a specific
milestone's feature actually needs them — validated in Milestone 4,
where `sidebar`/`sheet`/`dropdown-menu`/`popover`/`breadcrumb` were
added exactly when the Product Shell needed them, not before.

**Accessibility enforcement — convention/documentation vs. compile-time
types.** The initial approach (Milestone 3) documented "icon-only
buttons need `aria-label`" as a convention. That convention was broken
almost immediately, in the same milestone's own verification page, and
axe-core caught it as a critical violation. Milestone 3.1 replaced the
convention with a TypeScript discriminated union on `Button` that makes
`aria-label` a compile error to omit on icon sizes — verified against
real vendor code in Milestone 4 (`shadcn`'s own `SidebarTrigger` and
`SheetContent` close button both failed this stricter type, confirming
it catches real cases, not just contrived ones).

## Chosen Solution

- **Tokens**: CSS custom properties in `src/app/globals.css`, bridged
  into Tailwind's theme via `@theme inline`. Semantic color tokens
  (`success`, `warning`, `destructive`, `muted-foreground`) are
  measured against WCAG AA (4.5:1) using a purpose-built empirical
  script (Canvas2D-resolved sRGB, not guesswork or `getComputedStyle`,
  which preserves `oklab()`/`color-mix()` notation rather than
  resolving it) — not just asserted.
- **Primitives**: shadcn CLI-generated where the CLI's own registry
  covers the need; hand-built only where it doesn't (`Typography`).
  Never blindly re-run `add` against a file that's been customized —
  verify via `--dry-run`/`--diff`/`--view` first (a hard lesson from
  Milestone 4).
- **Composites**: `common/` components stay business-agnostic
  (`StatusBadge`'s status vocabulary is generic, not domain-specific)
  so features map their own states onto them rather than `common/`
  leaking feature logic.
- **Verification**: every design-system change gets rendered in a real
  browser (Playwright) and audited (`axe-core`) via a temporary,
  uncommitted preview — confirmed reverted (empty `git diff`) before
  the milestone is considered done. Static checks alone have not once
  caught this project's real accessibility defects.

## Trade-offs

- Base UI's smaller ecosystem (vs. Radix) means less prior art to
  search when something behaves unexpectedly — accepted, offset by
  direct source inspection (done repeatedly, successfully, across
  Milestones 3 and 4).
- Compile-time `aria-label` enforcement is stricter than the ecosystem
  norm and required a documented `nativeButton={false}` pattern when
  composing `Button` as a `Link` (Milestone 4) and a typed workaround
  for `mergeProps`'s stricter parameter typing (Milestone 3.1) —
  accepted; the alternative (convention-only) already failed once.
- The "core set now" component strategy means later milestones
  periodically need a "is this primitive available yet?" check before
  building — accepted; the `--dry-run` protocol from Milestone 4 makes
  this cheap and safe.

## Future Implications

- Any future `npx shadcn add <component>` must be preceded by
  `--dry-run` (and `--diff` if it reports an overwrite) — this is now
  a standing project rule, not a one-off precaution.
- New semantic color tokens (if ever added) should be measured with
  the same empirical contrast method used in Milestone 3.1, not
  eyeballed.
- The compile-time `aria-label` pattern on `Button` is a template for
  future components with a similar "this prop becomes required under
  condition X" shape, should one arise.