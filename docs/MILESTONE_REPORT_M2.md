# Milestone 2 Report

## Date

2026-07-10

---

## Objective

Establish WP Studio's project foundation ahead of any real UI or business
logic: install and configure the full dependency set (UI primitives,
forms, state, server-state, tables, charts), set up shadcn/ui and a
neutral, dark-mode-first theme token system, configure typography, wire
up Prettier/Husky/lint-staged, and add VSCode, environment, and GitHub
scaffolding. No application pages, dashboard, auth, or API code — pure
foundation, per the milestone's explicit scope.

---

## Completed Tasks

- Installed all specified dependency groups: UI (shadcn/ui, Base UI,
  Lucide, cva, clsx, tailwind-merge), forms (React Hook Form, Zod,
  `@hookform/resolvers`), state (Zustand), server state (TanStack
  Query), tables (TanStack Table), charts (Recharts).
- Initialized and configured shadcn/ui (neutral base color, CSS
  variables, `src/` + TS + import aliases auto-detected, Base UI as the
  primitive library).
- Rebuilt `src/app/globals.css` into a curated design-token system:
  color tokens (including `success`/`warning`, and a `destructive-foreground`
  the generated preset was missing), soft shadow scale, radius scale,
  transition-duration tokens, class-based dark mode, and an explicit
  `:focus-visible` accessibility rule.
- Configured Geist (primary) + Inter (fallback) typography via
  `next/font/google`.
- Configured Prettier (with `prettier-plugin-tailwindcss`), integrated
  `eslint-config-prettier`, and wired Husky + lint-staged to a
  pre-commit hook.
- Added `typecheck`, `format`, `format:check` npm scripts.
- Added `.vscode/settings.json` + `extensions.json` (recommended
  extensions only), `.env.example`, and `.github/` issue/PR templates +
  `CODEOWNERS`.
- Removed side effects from `shadcn init` that were out of scope: an
  auto-generated `button.tsx` component, and `shadcn` itself being
  listed as a runtime dependency instead of a dev tool.
- Found and fixed three real bugs during self-review (not requested,
  surfaced while verifying the generated output — see Architectural
  Decisions and Lessons Learned).
- Updated `PROJECT.md`, `ROADMAP.md`, `DEVLOG.md`; verified
  `typecheck`, `lint`, `format:check`, and `build` all pass; smoke-tested
  dev and production servers.
- Committed as `027740d` (pre-commit hook / lint-staged ran successfully
  on the real commit — first live validation of that pipeline).

---

## Files Created

```
.env.example
.github/CODEOWNERS
.github/ISSUE_TEMPLATE/bug_report.md
.github/ISSUE_TEMPLATE/feature_request.md
.github/PULL_REQUEST_TEMPLATE.md
.husky/pre-commit
.prettierignore
.prettierrc.json
.vscode/extensions.json
.vscode/settings.json
components.json
src/lib/utils.ts
```

---

## Files Modified

```
.gitignore
docs/DEVLOG.md
docs/PROJECT.md
docs/ROADMAP.md
eslint.config.mjs
package-lock.json
package.json
src/app/globals.css
src/app/layout.tsx
tsconfig.json
```

`tsconfig.json`'s diff is Prettier reformatting only (multi-line arrays)
— no semantic change.

---

## Dependencies Installed

Scoped to what this milestone added. `@eslint/eslintrc`, `tailwindcss`,
`next`, `react`, `react-dom`, `typescript`, `eslint`,
`eslint-config-next` predate Milestone 2 (Milestone 1) and are unchanged.

**Runtime (`dependencies`)**

| Package | Why |
| --- | --- |
| `@base-ui/react` | Unstyled, accessible primitive components (dialog, dropdown, tabs, etc.) that shadcn/ui's generated components are built on. |
| `@hookform/resolvers` | Bridges Zod schemas into React Hook Form's validation resolver API. |
| `@tanstack/react-query` | Server-state fetching/caching layer for the future Laravel API. |
| `@tanstack/react-table` | Headless table logic for dashboard data tables (sites, content lists, etc.). |
| `class-variance-authority` | Typed variant/style composition for component APIs (e.g. a `Button` with `variant="destructive"`). |
| `clsx` | Conditional className joining; used inside the `cn()` helper. |
| `lucide-react` | Icon set matching the shadcn/ui ecosystem. |
| `react-hook-form` | Form state and validation management. |
| `recharts` | Charting library for analytics dashboards. |
| `tailwind-merge` | Resolves conflicting Tailwind class strings; used inside `cn()` so component props can safely override default classes. |
| `tw-animate-css` | Tailwind v4-compatible animation utilities (successor to `tailwindcss-animate`), needed for Base UI's enter/exit transitions. |
| `zod` | Schema validation; pairs with React Hook Form and later API request/response validation. |
| `zustand` | Lightweight global client-state store. |

**Dev (`devDependencies`)**

| Package | Why |
| --- | --- |
| `eslint-config-prettier` | Disables ESLint stylistic rules that would otherwise fight Prettier's output. |
| `husky` | Manages git hooks declaratively and versions them in the repo, so every clone gets the same pre-commit hook via `npm install`. |
| `lint-staged` | Runs lint/format only against staged files at commit time, keeping the hook fast. |
| `prettier` | Code formatter; single source of truth for formatting style. |
| `prettier-plugin-tailwindcss` | Auto-sorts Tailwind utility classes into a canonical order. |
| `shadcn` | CLI for pulling component source into the repo (`npx shadcn add ...`). Dev-only — never imported at runtime, moved here after `init` incorrectly added it as a runtime dependency. |

None of the new runtime packages are imported by application code yet
— installing ahead of use is the explicit point of this milestone.

---

## Configuration Changes

- **`components.json`** (new) — shadcn/ui config: `base-nova` style,
  neutral base color, CSS variables on, `src/app/globals.css` as the
  CSS entry point, standard `@/components`, `@/lib`, `@/hooks` aliases.
- **`src/app/globals.css`** — full rewrite. Kept the CLI's generated
  neutral OKLCH scale; added missing tokens (`success`, `warning`,
  `destructive-foreground`, shadow scale, duration tokens); added
  curated `data-*` custom variants and a `no-scrollbar` utility inline
  instead of importing the CLI's bundled stylesheet; added a
  `:focus-visible` rule.
- **`src/app/layout.tsx`** — added `Inter` as a second `next/font/google`
  loader, wired into the `<html>` className alongside Geist.
- **`eslint.config.mjs`** — added `eslint-config-prettier` to the flat
  config array.
- **`package.json`** — added `typecheck`, `format`, `format:check`,
  `prepare` scripts and a `lint-staged` block.
- **`.husky/pre-commit`** (new) — runs `npx lint-staged`, replacing the
  Husky default `npm test` placeholder.
- **`.gitignore`** — added `!.env.example` to un-ignore the template
  file that the pre-existing `.env*` pattern was silently excluding.
- **`.vscode/settings.json` / `extensions.json`** (new) — Prettier as
  default formatter, format-on-save, ESLint auto-fix on save, Tailwind
  IntelliSense class-regex for `cn()`/`cva()`; three recommended
  extensions (ESLint, Prettier, Tailwind CSS IntelliSense).

---

## Architectural Decisions

- **Base UI over Radix.** The shadcn CLI's current major version
  (4.13.0) defaults to Base UI as its primitive library, and it's the
  actively maintained successor built by the same team behind Radix and
  Floating UI. Went with the CLI's own default rather than overriding
  to the older Radix path.
- **Did not import `shadcn`'s bundled `tailwind.css`.** It mixes
  genuinely needed infrastructure (data-state variants, accordion
  keyframes) with decorative shimmer-text and scroll-fade utilities
  that directly contradict the project's "no unnecessary animations"
  design brief. Inlined only the needed pieces instead, which also
  avoids an undocumented runtime coupling to the CLI package's internal
  file structure.
- **Class-based dark mode (`.dark` on `<html>`), not
  `prefers-color-scheme`.** Keeps a manual theme toggle possible in a
  later milestone rather than being locked to OS preference.
- **Geist primary + Inter explicit fallback**, both self-hosted via
  `next/font/google`. Verified in the compiled build that Inter isn't
  eagerly preloaded — it's a pure CSS fallback with negligible cost
  unless Geist fails to load.
- **`shadcn` moved to `devDependencies`.** It's a codegen CLI, not a
  runtime import; `init` placed it in `dependencies` by default.

---

## Risks

1. `components.json`'s `"style": "base-nova"` ties future `npx shadcn
   add` calls to a specific named preset pulled from shadcn's registry.
   If a future CLI version renames or removes that preset, `add` could
   behave unexpectedly or require reconfiguration. Low probability, but
   worth a quick smoke test before Milestone 3 generates real
   components.
2. `@base-ui/react` is young (v1.6.0) relative to the long-established
   Radix ecosystem — smaller body of community precedent/Stack Overflow
   answers if something behaves unexpectedly, despite active
   maintenance by a credible team.
3. A moderate `npm audit` advisory (PostCSS XSS via unescaped
   `</style>` in stringify output) is nested inside Next.js's own
   `node_modules/next/node_modules/postcss` and isn't independently
   fixable without a breaking Next.js downgrade to 9.x. Low real-world
   exploitability for a build-time CSS stringifier in this project's
   context, but should be rechecked on every Next.js patch bump.
4. Zero automated test coverage exists yet (expected — Testing is
   Milestone 10), so configuration regressions in this foundation would
   currently only surface via manual verification, not CI, until
   Milestone 11.

---

## Technical Debt

1. `src/styles/` (created in Milestone 1's feature-first skeleton) is
   still empty and unused — all styling currently lives in
   `src/app/globals.css`. Its intended purpose relative to `globals.css`
   is undocumented. Should either get a defined role (e.g.
   component-scoped CSS) or be removed.
2. `success`/`warning` OKLCH values were chosen for reasonable contrast
   but haven't been visually verified against real rendered components
   — none exist yet to check against.
3. Some `CODING_STANDARDS.md` rules are lint-enforced (confirmed
   `@typescript-eslint/no-explicit-any` resolves to `"error"` via
   `eslint-config-next`'s TypeScript config) but others, like the
   ~300-line file guideline, have no automated backstop and rely on
   review discipline.

---

## Known Issues

None outstanding. Three bugs were found and fixed within this milestone
(not left open): a self-referencing `--font-sans` CSS variable that
would have silently fallen back to the browser default font, a stray
un-neutralized blue hue in dark-mode `--sidebar-primary`, and the
`.gitignore` pattern silently excluding `.env.example`.

---

## Recommendations

1. Before Milestone 3 generates real primitives, do a quick sanity
   check that `npx shadcn add button` still resolves the `nova` preset
   as expected — the CLI's release cadence has been fast and its
   structure changed significantly since the last training-data
   snapshot.
2. Decide the fate of `src/styles/` (define its purpose, or delete it)
   before Milestone 3 adds more component-level styling decisions on
   top of an already-ambiguous structure.
3. Visually spot-check the `success`/`warning` tokens once Milestone 3
   introduces the first badge/alert component that uses them.
4. Recheck the PostCSS audit advisory on the next Next.js patch release.

---

## Lessons Learned

- The shadcn CLI has changed substantially in its current major version
  (registry-based presets and a Base UI/Radix choice, replacing the old
  style/base-color prompts) — prior assumptions about its flags and
  output didn't hold, and verifying actual `--help` output and
  generated files before trusting them caught real problems (the
  auto-generated `button.tsx`, the misplaced runtime dependency).
- Vendor-generated scaffolding is a good starting point but not
  automatically correct: two genuine bugs (a self-referencing CSS
  variable, an un-neutralized stray color) shipped in the CLI's own
  preset output and only surfaced through manual review, not through
  any build or lint failure — `tsc`, ESLint, and `next build` all
  passed with the bugs still in place.
- Broad `.gitignore` glob patterns (`.env*`) can silently swallow files
  meant to be committed (`.env.example`); worth an explicit `git status
  --ignored` check whenever a new template/example file is added
  alongside an existing broad ignore rule.

---

## Ready for Next Milestone?

**YES.** The foundation is verified (`typecheck`, `lint`,
`format:check`, and `build` all green), documented, and committed. The
two open items (preset-naming risk, unused `src/styles/`) are minor,
non-blocking, and don't require resolution before starting Milestone 3.

---

## Overall Grade

**A-**

All deliverables from the milestone spec were completed and verified.
The review process caught and fixed three real bugs in vendor-generated
output rather than accepting it uncritically, and documentation stayed
current throughout. Docked from a full A for two small, avoidable pieces
of debt (the undocumented, unused `src/styles/` directory and the
not-yet-visually-verified `success`/`warning` tokens) and for taking on
a dependency-naming coupling (`components.json`'s preset name) to a
fast-moving external CLI without a documented mitigation plan beyond
"recheck it later."

---

## If Improvements Are Required Before Milestone 3

In priority order (not implemented — reporting only, per instructions):

1. Smoke-test `npx shadcn add button` against the `nova` preset before
   generating real primitives in Milestone 3.
2. Decide and document the purpose of `src/styles/`, or remove it.
3. (Non-blocking) Visually verify `success`/`warning` contrast once the
   first component using them exists.