# Devlog

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
