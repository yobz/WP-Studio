# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-22 — End of Milestone 20 (Production Release) — Roadmap Complete

**Milestone state.** Milestone 20 is implemented and validated —
`docs/adr/0018-production-release.md` has the full reasoning,
`docs/MILESTONE_REPORT_M20.md` the full independent review.
**Not yet committed or pushed** — waiting on explicit approval per
this project's standing rule. This closes `docs/ROADMAP.md` — all 20
planned milestones across v0.7 through v1.0 are now complete.

**This was deliberately an audit-and-polish milestone, not a feature
one** — explicit user guidance to resist adding new capabilities.
Nothing here is new application code; every change is a dependency
patch, a documentation consistency fix, or a review producing new
documentation (disaster recovery).

**Four things worth knowing before touching this again.**

1. **A real, safe Guzzle security fix is identified but not applied.**
   `composer update guzzlehttp/guzzle --with-all-dependencies` — safe
   within Laravel's existing `^7.8.2` constraint, blocked this session
   only by `repo.packagist.org` being unreachable from this
   environment (confirmed host-specific — `npm`'s registry worked
   fine at the same time). Run it the next time packagist is
   reachable; nothing else needs to change.
2. **Two `npm audit` findings (`postcss`, `sharp`) can't be fixed from
   this repo.** Both are pinned inside Next.js 15's own bundled
   dependency tree (`npm view next@15.5.21 dependencies.postcss`
   confirms a hard pin unchanged in the latest available patch).
   `npm audit fix --force`'s suggested fix — downgrading Next.js to
   9.3.3 — is not a real option. Re-check on every future Next.js
   version bump; don't force this in the meantime.
3. **`README.md`'s `Status` section needs manual updates.** It went
   four milestones stale (still said "Milestone 15... complete") before
   this milestone's audit caught it — no other milestone's own
   doc-update step naturally revisits the root README. Update it
   alongside `docs/PROJECT.md`'s Status line at the end of any future
   milestone, not as an afterthought.
4. **`docs/DEPLOYMENT.md` now has a §9 Disaster Recovery section** —
   read it before any real incident response. Its core claims (every
   migration has a real rollback, Railway/Vercel both support instant
   deploy rollback) were verified this session, not assumed.

**Immediate next step.** None planned — `docs/ROADMAP.md` has no
further milestones. Real, accurately-documented future work remains
(the two dependency items above; every ADR's own Deferred/Future
Evolution sections — thumbnail generation, a full CSP, DNS-rebinding
protection, virus scanning, the actual live deployment via
`docs/DEPLOYMENT.md`) — none of it silently dropped, all of it named
with reasoning. If a new work item is wanted, it's a genuinely new
initiative at this point, not a numbered milestone continuing a plan.

**Known live gotchas (carried forward, still accurate).**
- Docker (Milestone 15/19): `docker compose up` for dev,
  `docker build -f docker/production/php.Dockerfile .` for the
  production image — both verified working; Docker Desktop needs to
  actually be running first.
- `composer`/`npm` commands against their respective registries can
  hit real, host-specific connectivity issues in this environment —
  confirmed this session that `repo.packagist.org` and
  `registry.npmjs.org` can behave differently at the same moment.
  Don't assume a `composer` network failure means the whole network is
  down; check `npm` (or a direct `curl`) too before concluding it's
  systemic.
- Local PHP (XAMPP) has `pdo_pgsql`'s DLL present but not enabled by
  default — `php -d extension=php_pdo_pgsql.dll <command>` loads it
  for a single invocation without touching the system `php.ini`.
- A stack trace pointing entirely inside a dependency's own bundled
  internals, right after a `node_modules` change, is this project's
  recurring stale `.next`/`bootstrap/cache` build-cache pattern — see
  `docs/ENGINEERING_JOURNAL.md`.
- `tests/Pest.php`'s global `beforeEach()` must stay chained onto
  `pest()->extend(...)->in('Feature')` — a standalone top-level
  `beforeEach()` silently applies to nothing (see the Journal's
  2026-07-22 entry from Milestone 19).
- Never print any part of an API key/credential/DSN/DB password into
  tool output or logs.
- Demo login: `test@example.com` / `password`.

**Validation status as of this session.** Backend: `php artisan test`
— **146/146 passing**, unchanged (no application code touched this
milestone). `./vendor/bin/pint --test` — clean. Frontend:
`npm run test` — **20/20 passing**, unchanged. `typecheck`/`lint`/
`build` all clean; dashboard First Load JS unchanged at 144kB
(confirms `npm audit fix` didn't regress Milestone 17's bundle-size
work). `composer audit` — 4 advisories found, fix verified safe, not
applied (network). `npm audit` — 7 → 6 vulnerabilities, each
individually investigated.
