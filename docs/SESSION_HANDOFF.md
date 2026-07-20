# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-20 — End of Milestone 16 (Frontend Testing & CI/CD)

**Milestone state.** Milestone 16 is implemented, validated, documented,
committed, and pushed — see `docs/MILESTONE_REPORT_M16.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. The first live
GitHub Actions run (triggered by the push) failed on a real bug — see
gotcha #4 below — fixed in a same-day follow-up commit; the next run
passed both jobs cleanly.

**New: the frontend has real automated tests, and both apps have CI.**
`npm run test` (Vitest + React Testing Library) — 20 tests, 5 files,
deliberately scoped to critical flows (`LoginForm`,
`AiAssistantPreview`, two mappers, one hook), not every component.
`.github/workflows/ci.yml` — `frontend`/`backend` jobs on native
runners, running on every PR and push to `master`. Full reasoning in
`docs/adr/0014-frontend-testing-and-ci.md`.

**Three things worth knowing before touching this again.**

1. **New test files go next to the code they cover** (`foo.ts` →
   `foo.test.ts`), not in a parallel `__tests__` tree — see
   `docs/CODING_STANDARDS.md`'s Testing section. Mock only at the
   `services/api/*.service.ts` boundary; never mock React Query or the
   component tree.
2. **`@vitejs/plugin-react` is pinned to `^5.2.0`, not latest.** Version
   6 introduced an optional peer dependency on a Babel-8-based plugin
   that conflicts with `shadcn`'s own Babel-7 tree. If a future
   `npm install`/audit wants to bump it, expect this conflict to
   resurface — re-read `docs/adr/0014-frontend-testing-and-ci.md`
   before forcing it past the warning.
3. **A stack trace pointing entirely inside a dependency's own bundled
   internals, right after a `node_modules` change, is this project's
   now-recurring "stale `.next`/`bootstrap/cache` build cache" pattern
   in a new shape** (Milestones 6, 13, 15, now 16) — delete `.next/`
   before investigating the error itself. See
   `docs/ENGINEERING_JOURNAL.md`'s 2026-07-20 entry.
4. **`backend/tests/Unit/` no longer exists, deliberately.** It was an
   empty, untracked directory (git doesn't track empty directories)
   left over since Milestone 6 — present locally by accident, absent on
   any fresh clone, and the cause of this milestone's first live CI
   failure (`phpunit.xml` still referenced it as a testsuite). Every
   test in this project is Feature-level; don't recreate `tests/Unit/`
   without also adding it back to `phpunit.xml`'s `<testsuites>` and
   `tests/Pest.php`'s bindings — otherwise the exact same failure
   recurs. See `docs/ENGINEERING_JOURNAL.md`'s matching dated entry.

**Immediate next step.** Milestone 17 (Performance & Scalability) is
next per `docs/ROADMAP.md` — but is **explicitly not started**, waiting
for approval per the milestone lifecycle's standing rule.

**Known live gotchas (carried forward, still accurate).**
- Docker (Milestone 15): `docker compose up` is a real, working
  alternative to the bare-metal setup — see that milestone's own
  Session Handoff entry in `docs/DEVLOG.md` history for its specific
  gotchas (bind-mount write permissions on Windows, the `backend/`
  watcher shadow, `composer install`/`npm install` not auto-re-running
  after a dependency change).
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, and a brief wait after
  `page.goto()` resolves before interacting (clicking before hydration
  finishes falls back to native HTML form submission) — documented
  since Milestone 11, sharpened in Milestone 15.
- `axe-core` is a real transitive dependency, never delete it during
  cleanup. `playwright` is installed with `--no-save` and uninstalled
  again after ad hoc live verification, every time.
- Never print any part of an API key/credential into tool output or
  logs.
- Demo login: `test@example.com` / `password`.

**Validation status as of this session.** Frontend: `npm run test` —
**20/20 passing**. `typecheck`/`lint`/`build` all clean. Backend:
`php artisan test` — **142/142 passing** (unchanged).
`./vendor/bin/pint --test` (full-repo) — clean, after fixing 7
pre-existing issues found during this milestone's own validation. CI:
run live twice — the first run failed on the `tests/Unit` bug above,
the second (after the fix, re-verified against a genuine fresh
`git clone` before pushing) passed both jobs. See
`docs/MILESTONE_REPORT_M16.md`.
