# 0014 — Frontend Testing & CI/CD

**Status:** Accepted (Milestone 16)

## Decision

Vitest + React Testing Library for frontend tests, covering critical
flows (forms, stateful widgets, hooks with real logic) and pure logic
(mappers), not every component. One GitHub Actions workflow, two
parallel jobs (`frontend`, `backend`) running on native runners
(`setup-node`/`setup-php`), not inside the Milestone 15 Docker images —
CI and local dev are different concerns with different constraints, and
Docker's own ADR already scoped that setup to developer experience, not
CI. No test matrix (one Node version, one PHP version — the same ones
Milestone 15's containers use) and no separate lint/format CI step
beyond what the brief asked for. This milestone follows explicit
guidance to prefer the simpler approach wherever it demonstrates the
same skill: a two-job workflow proves the same competency as an
elaborate matrix, for a portfolio project with one deployment target.

## Context

Every milestone review since M5 has flagged the same gap: the backend
has had a real Pest suite since Milestone 6 (142 tests by Milestone 15);
the frontend has had zero automated tests. This milestone closes that
gap and adds the CI gate that makes both suites (plus lint/typecheck/
build) enforceable on every PR, not just something a developer might
remember to run locally.

## Vitest, Not Jest

Next.js 15 ships first-class Vitest support and this project's Vite-free
zero-config setup (no `next.config.ts`) needs no Jest-specific webpack/
SWC transform configuration Vitest doesn't already handle via
`@vitejs/plugin-react`. Faster (native ESM, no CommonJS transform step)
and simpler to configure for this project's actual shape — not chosen
for any Jest deficiency, chosen because it's the smaller amount of
configuration for what this project needs.

**One dependency-resolution snag, resolved by pinning, not forcing.**
`@vitejs/plugin-react@6` (latest) declares an optional peer on
`@rolldown/plugin-babel`, which wants Babel 8; `shadcn`'s own dependency
tree wants Babel 7 — a real conflict `npm install` correctly refused to
resolve silently. Pinned `@vitejs/plugin-react@^5.2.0` (the last stable
major before that optional peer existed) instead of `--legacy-peer-deps`
or `--force`, which would have accepted an unverified, potentially
broken resolution rather than avoiding the conflict.

## What Gets Tested, Deliberately Bounded

Five files, twenty tests: two pure mapper functions
(`mapSummaryToKpis`, `mapSystemHealth`), one form component with real
validation/error-branching logic (`LoginForm`), one component with the
richest state machine in the app (`AiAssistantPreview` — idle/
generating/completed/failed), and one hook (`useAiJob`, the
`enabled`-gating behavior). Every test mocks at the actual network
boundary (`services/api/*.service.ts` functions) — never React Query
internals, never the component tree — so what's tested is the real
render → user interaction → real hook/component logic → real assertion
path, with only the actual HTTP call stubbed.

**Deliberately not tested**: presentational components with no
branching logic (a `StatusBadge`, a `Card`), and every other page/
widget this project has. Exhaustive component coverage for a portfolio
project demonstrates the same testing competency as targeted coverage
of the flows that actually have logic worth verifying — the second is
also the honest answer to "what would you actually test on a real
team," which is the standard this milestone is calibrated to.

## CI: Native Runners, Not the Docker Images

Considered running CI against the Milestone 15 Docker Compose stack
directly (`docker compose build && docker compose run ... test`) for
maximum local/CI parity. Rejected: that stack's own ADR
(`docs/adr/0013-docker-development-environment.md`) scoped it
explicitly to *developer experience* — bind mounts, `next dev`, dev-
shaped images — none of which CI needs or benefits from. `setup-node`/
`setup-php` with dependency caching is faster, simpler to debug from CI
logs (no container layer to look through), and is what the vast
majority of Laravel + Next.js projects use for exactly this reason.
Docker-based CI is a real future option once Milestone 19 needs
production images to test against — not needed today.

## A Pre-Existing Gap This Milestone's Own CI Gate Would Have Failed On

`./vendor/bin/pint --test` (a full-repo sweep) found 7 style issues in
files this milestone never touched — every prior milestone validated
with `--dirty` (changed files only), so full-repo drift had never
surfaced. Fixed before adding Pint to the CI workflow, not after: a CI
gate that's red on its first run teaches a team to ignore it, not trust
it. All seven were mechanical (`ordered_imports`, `no_unused_imports`,
`fully_qualified_strict_types`) — `./vendor/bin/pint` applied and
re-verified with a full test run before proceeding.

## Rejected Alternatives

**End-to-end (Playwright) tests as part of this milestone.** The brief
specified Vitest + React Testing Library — component/unit-level
coverage, not full-browser E2E. Every milestone since M4 has already
used Playwright for one-off manual live verification; formalizing it
into a permanent, CI-run E2E suite is real, valuable future scope but a
different tier of testing than what this milestone asked for.

**A test matrix across Node/PHP versions.** This project targets one
Node version and one PHP version in every other context (Milestone 15's
Docker images, this repo's own `composer.json`/`package.json`
constraints) — a matrix would test version combinations nothing in this
project's actual deployment story uses.

## Future Evolution

- **Milestone 15's Docker images as a CI target** once Milestone 19
  needs to validate an actual production image, not just source code.
- **Playwright E2E in CI** — a real, separate future milestone if this
  project's manual live-verification practice needs to become a
  permanent, automated gate.
- **Coverage reporting** — not configured; revisit if a real coverage
  threshold becomes a meaningful signal at this project's current test
  count, rather than a vanity metric.
