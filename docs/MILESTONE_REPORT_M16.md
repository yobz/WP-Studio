# Milestone 16 Report

## Date

2026-07-20

---

## Objective

Close the testing asymmetry flagged in every milestone review since M5
(142 backend Pest tests, zero frontend tests) with Vitest + React
Testing Library coverage for critical frontend flows. Add GitHub Actions
to run lint, typecheck, tests, and production builds for both frontend
and backend on every pull request. Per explicit user guidance at the
start of this milestone: prefer the simpler of two approaches wherever
it demonstrates the same skill — no unnecessary enterprise complexity
for a portfolio project.

---

## Executive Summary

Milestone 16 is complete. Vitest + React Testing Library, chosen over
Jest for this project's Vite-free zero-config Next.js setup (no
Jest-specific transform configuration to write or maintain). Coverage is
deliberately bounded rather than exhaustive: 20 tests across 5 files —
a form component with real validation/error-branching logic
(`LoginForm`), the richest stateful widget in the app
(`AiAssistantPreview`, all four idle/generating/completed/failed
states), two pure mapper functions, and one hook's conditional-fetch
behavior. Every test mocks at the actual network boundary
(`services/api/*.service.ts`), never React Query internals — what's
under test is the real render → interact → logic → assert path.

One GitHub Actions workflow, two parallel jobs on native runners
(`setup-node`/`setup-php`), not the Milestone 15 Docker images — that
stack's own ADR scoped it to developer experience, not CI, and native
runners are faster and simpler to debug from CI logs. No version
matrix: one Node version, one PHP version, matching what Milestone 15's
containers already use.

**Two real findings, both fixed before being called done, neither
hidden.** A full-repo `pint --test` (every prior milestone had only
ever run `--dirty`) surfaced 7 pre-existing style issues in files this
milestone never touched — fixed first, so the new CI gate started green
rather than red on day one. Separately, installing the new test
tooling triggered a peer-dependency conflict (`@vitejs/plugin-react@6`'s
optional Babel-8 peer against `shadcn`'s Babel-7 tree) — resolved by
pinning the last compatible major version, not by forcing an unverified
resolution.

---

## Architecture Review

Read `docs/ROADMAP.md`'s Milestone 16 entry, `docs/CODING_STANDARDS.md`,
`docs/adr/0013-docker-development-environment.md` (to decide CI's
relationship to the Docker setup), and the current frontend structure to
identify which flows actually carry logic worth testing.

---

## Architecture Drift Review

No existing test infrastructure, CI configuration, or `.github/workflows/`
directory — genuinely greenfield. The one real drift question — should
CI reuse the Milestone 15 Docker images — was evaluated directly
against that milestone's own ADR and answered no: Docker Compose there
is explicitly scoped to developer experience (bind mounts, dev-shaped
images), not a CI concern, and reusing it would import that scope
mismatch into this milestone rather than solve anything.

---

## What Was Built

**Frontend**: `vitest.config.ts` (jsdom environment, `@vitejs/plugin-react`,
`@/` path alias matching `tsconfig.json`), `vitest.setup.ts`
(`@testing-library/jest-dom` matchers), `src/test/render.tsx`
(`renderWithQueryClient` helper — a fresh `QueryClient` per test with
retries disabled, so a mocked failure resolves immediately instead of
waiting out the app's production retry/backoff). Five test files:
`map-summary-to-kpis.test.ts`, `map-system-health.test.ts`,
`login-form.test.tsx`, `ai-assistant-preview.test.tsx`,
`use-ai-job.test.tsx`. `npm run test` (CI) / `npm run test:watch`
(local) added to `package.json`.

**CI**: `.github/workflows/ci.yml` — `frontend` job (checkout,
`setup-node@20` with npm caching, `npm ci`, typecheck, lint, test,
build) and `backend` job (checkout, `setup-php@8.3` with the extensions
this app uses, `ramsey/composer-install`, `.env` bootstrap, Pint, `artisan
test`), both on `pull_request` and `push: master`.

**Documentation**: `docs/adr/0014-frontend-testing-and-ci.md`, a
`## Testing` section in `docs/CODING_STANDARDS.md` (test files
co-located with source, critical-flows-not-everything, mock at the
network boundary), plus a small drive-by correction in the same file
(`main` → `master`, matching this repo's actual default branch — noticed
while writing the CI trigger).

---

## Validation

- `npm run test` — **20/20 passing** across 5 files.
- `npm run typecheck` / `npm run lint` — clean, including the new test
  files.
- `npm run build` — clean production build (hit and fixed a stale
  `.next` cache crash during this milestone's own validation — see
  Engineering Journal's dated entry; unrelated to any code this
  milestone wrote, a recurrence of this project's known build-cache-
  staleness pattern in a new error shape).
- `php artisan test` — **142/142 passing**, unchanged.
- `./vendor/bin/pint --test` (full-repo) — clean, after fixing the 7
  pre-existing issues found (see Executive Summary).
- `.github/workflows/ci.yml` — validated for syntactic correctness
  (`js-yaml` parse) and structural correctness locally, then actually
  run: the first live push caught a real, genuine bug the local
  environment could never have revealed — see "The First Real CI Run"
  below.

### The First Real CI Run Found a Bug No Local Check Could

The Backend job failed on its first execution: `php artisan test`
exited instantly with `Test directory ".../tests/Unit" not found`.
`backend/tests/Unit/` turned out to be a genuinely empty directory —
present on the local machine since Milestone 6, but never tracked by
git (git doesn't track empty directories), so it existed locally by
historical accident and didn't exist at all on GitHub Actions' clean
checkout. `phpunit.xml` still referenced it as a configured testsuite.
This project has never actually had a unit test (all 142 tests are
Feature-level) — fixed by removing the phantom `Unit` testsuite entry
from `phpunit.xml` and its matching dangling reference in `Pest.php`,
not by recreating an empty placeholder directory for hypothetical
future tests. Verified against a genuinely fresh `git clone` (not just
"ran locally again") before pushing the fix, given the first push had
already shown local state can diverge from what a clean checkout sees.
Second run: both jobs green. Full account in
`docs/ENGINEERING_JOURNAL.md`'s dated entry.

---

## Self Review

Re-read `vitest.config.ts` and every test file with fresh eyes: no
over-mocking (React Query and the component tree are always real; only
the network call is stubbed), no snapshot tests (explicit assertions
throughout, per this project's "prefer readable over clever" standard),
no test asserting implementation detail instead of observable behavior.
Confirmed `CODING_STANDARDS.md`'s new Testing section accurately
describes what the five test files actually do, not an aspirational
policy divorced from the real code.

---

## Production Readiness

CI itself doesn't change what ships — it's a gate on what already
merges. The one operationally relevant addition: a broken `build` or a
failing test suite now blocks a PR automatically instead of depending on
a developer remembering to run either locally first.

---

## Technical Debt Resolved

- **Zero frontend automated tests**, flagged in every milestone review
  since M5 — resolved for critical flows.
- **No CI gate of any kind** — every check (lint, typecheck, tests,
  build) was previously manual, run by a developer's own discipline
  rather than enforced.
- **7 pre-existing Pint style issues**, invisible until this milestone's
  first full-repo sweep — fixed.

---

## Deferred Work

- **End-to-end (Playwright) tests in CI** — this milestone's brief
  specified Vitest + RTL; formalizing this project's existing ad hoc
  Playwright verification practice into a permanent CI suite is real,
  separate future scope.
- **Coverage reporting/thresholds** — not configured; not a meaningful
  signal at 20 tests.
- **CI against the Milestone 15 Docker images** — a real option once
  Milestone 19 needs to validate an actual production image.

---

## Risks

- ~~**This CI workflow has not yet run against a real GitHub Actions
  execution**~~ **Resolved.** It has now, twice: the first run caught a
  real bug (see "The First Real CI Run" above); the second, after the
  fix, passed both jobs cleanly.
- **20 tests is a real but narrow safety net** — critical flows are
  covered; a regression in an untested area (most pages/widgets) would
  still only be caught by manual verification or the backend's own test
  suite where applicable, same as before this milestone.

---

## Recommendation for Milestone 17

Per `docs/ROADMAP.md`, Milestone 17 (Performance & Scalability) is next
— Redis-backed caching where justified, query optimization, and
frontend bundle/loading performance, continuing to follow
`docs/adr/0005-domain-model.md`'s standing guidance against premature
optimization. Waiting for explicit approval before starting, per this
milestone's own stop condition.
