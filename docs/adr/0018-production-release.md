# 0018 — Production Release

**Status:** Accepted (Milestone 20)

## Decision

This milestone is deliberately **an audit and polish pass, not a
feature milestone** — explicit user guidance at the start: resist
adding new capabilities, focus on validating and documenting what
already exists. Every change here is one of three kinds: a dependency
security patch within an existing version constraint, a documentation
fix closing a real gap between what the docs claimed and what the
codebase actually does, or a review producing a new document (disaster
recovery) rather than new application code. Nothing here is a new
integration, a new endpoint, or a new architectural decision.

## Context

`docs/ROADMAP.md`'s Milestone 20 entry: final architecture review,
production readiness audit, deployment checklist, disaster recovery
review, documentation cleanup, dependency audit, final polish —
"resolve or explicitly document every deferred decision from
Milestones 1–19 before closing the project." Twenty milestones across
five releases (v0.7 through v1.0) accumulate real documentation drift
even when each individual milestone's own docs were accurate at the
time it was written — a later milestone resolving something an earlier
ADR named as deferred doesn't retroactively update that earlier ADR
unless someone goes back and does it. This milestone is that pass.

## Dependency Audit

**Backend (`composer audit`)**: 4 advisories, all in `guzzlehttp/
guzzle` (medium severity — cookie/redirect header handling issues),
all fixed in `7.15.1`+. Laravel's own constraint (`^7.8.2`) already
permits the fix — `composer update guzzlehttp/guzzle
--with-all-dependencies` is a safe, zero-risk patch update. **Not
applied this session**: `repo.packagist.org` was unreachable from this
environment (confirmed via direct `curl` — `registry.npmjs.org`
responded normally at the same time, so this was host-specific, not a
general outage) across multiple retries with extended timeouts.
Documented here as a concrete, safe, ready-to-run next step rather than
silently skipped or falsely claimed done.

**Frontend (`npm audit`)**: 7 advisories, resolved to 6 by `npm audit
fix` (non-force) — `fast-uri` (a transitive dependency of `shadcn`'s
own dev-only MCP SDK tooling) patched cleanly.

Two remaining vulnerabilities (`postcss` moderate, `sharp` high) are
both pinned **inside Next.js 15.5.20/15.5.21's own bundled dependency
tree**, not this project's direct dependencies — confirmed via `npm
view next@15.5.21 dependencies.postcss` (hard-pinned at `8.4.31`,
unchanged in the latest available 15.x patch) and `dependencies.sharp`
(capped at `^0.34.3`, which cannot reach the `0.35.0+` fix under
semver's caret-range rules for `0.x` versions). `npm audit fix
--force`'s suggested resolution — downgrading Next.js to `9.3.3` — is
not a real fix, it's `npm`'s resolver failing to find a better answer
within the installed tree; a genuine fix requires Next.js's own team
to bump these internal pins in a future release. The `postcss` finding
specifically is not new: `docs/DEVLOG.md`'s Milestone 1 entry already
named "a moderate `postcss` audit advisory... nested inside Next.js's
own `node_modules`... not fixable without a breaking Next.js downgrade"
— this milestone's audit re-confirms that finding still holds, five
releases later, and adds the newer `sharp` finding to the same
category.

One vulnerability (`@hono/node-server`, moderate, Windows-specific path
traversal) is a transitive dependency of `shadcn`'s own dev-only MCP
SDK tooling — never runs as part of the deployed application, only
during `npx shadcn add` at development time. `npm audit fix --force`'s
suggested resolution would *downgrade* the installed `shadcn` (4.13.0
→ 3.8.3), a real regression for a dev-tool-only, low-exploitability
finding. Reviewed and accepted as-is.

## Documentation Consistency Audit

Searched every ADR, `docs/PROJECT.md`, `docs/ENGINEERING_JOURNAL.md`,
and the root `README.md` for forward-references to milestones that had
since completed — anywhere still describing something as "deferred to
Milestone 19" or "not yet decided" after Milestone 19 actually
resolved it. Found and fixed real staleness in **eight files** that
Milestone 19's own documentation pass (necessarily focused on the new
ADR and `PROJECT.md`) hadn't touched: `docs/adr/0004`,
`0006`, `0007`, `0009`, `0010` (two separate locations), `0013`,
`0014`, and `docs/ENGINEERING_JOURNAL.md`'s Future Backlog. Each got a
strikethrough-plus-resolution-note in the same style established since
Milestone 9, not a rewrite — these are historical decision records,
and the goal is an accurate current status annotation, not revising
history.

**The root `README.md` was four milestones stale** — its own `Status`
section still read "Milestone 15 (Docker Development Environment)
complete," unchanged since that milestone, and its stack description
still called PostgreSQL a "MySQL-candidate" after Milestone 19 actually
decided and verified it. Both fixed. This is exactly the kind of drift
a documentation-cleanup milestone exists to catch — the most-visible
file in the repository was also the most stale, because no single
milestone's own doc-update step naturally touches it.

One real, still-open item surfaced (not stale, just worth
re-confirming): `docs/adr/0010-media-platform.md` named thumbnail/
responsive-image generation as Milestone 17's "natural next consumer."
Milestone 17 happened and didn't build it — its actual measured hot
paths were Posts pagination and the WordPress sync N+1, not media
rendering. Updated the ADR to reflect that accurately (real, still
open, no longer tied to a specific future milestone number) rather
than leave it implying a milestone that already passed will still
pick it up.

## Production Readiness Audit

Verified directly rather than re-asserted from memory of earlier
milestones' own claims:

- **No raw SQL anywhere in the application** —
  `grep -rn "DB::raw\|DB::statement\|DB::select\|whereRaw\|selectRaw" app/`
  returns nothing; every query goes through Eloquent's parameter-bound
  query builder.
- **No `dangerouslySetInnerHTML` anywhere in the frontend** — React's
  own escaping is never bypassed.
- **Every migration has a real, non-empty `down()` method** — checked
  programmatically across every file in `database/migrations/`, not
  assumed; a genuine finding feeding directly into this milestone's
  Disaster Recovery review (`docs/DEPLOYMENT.md` §9).
- **No `.env` file has ever been committed** — `git log --all -- '**/.env'`
  returns empty (re-confirmed; first verified in Milestone 19).
- Rate limiting, security headers (including HSTS), the DNS-resolution
  SSRF check, encrypted WordPress credentials at rest, and Sanctum's
  CSRF-cookie handling were all verified in place by earlier milestones
  (`docs/adr/0006`, `0007`, `0009` credential encryption test,
  `0017`) — re-confirmed present, not re-implemented.

## Disaster Recovery Review

New `docs/DEPLOYMENT.md` §9 — a review of existing platform mechanisms
(Railway/Vercel deployment rollback, Laravel migration rollback,
managed Postgres backups, R2 object versioning), not new
infrastructure. Every claim in it is either a verified fact about this
codebase (the `down()` method audit above) or a documented platform
feature the operator needs to actually enable (Railway's backup
add-on, R2 versioning are opt-in, not automatic) — written so the gap
between "the platform supports this" and "this project has turned it
on" is explicit, not assumed closed.

## Final Architecture Review

No architectural changes this milestone, by design. The review
confirmed the project's own stated architecture (feature-first
frontend, service-layer backend, one accepted ADR per real decision)
remained internally consistent across all twenty milestones — the
documentation audit above was the actual mechanism of this review, not
a separate step: verifying every ADR's claimed status is currently
accurate *is* the architecture review for a project whose ADRs are the
authoritative architecture record.

## Rejected Alternatives

**Forcing every `npm audit fix --force` / attempting further
composer network retries.** Rejected — both would have introduced
real regressions (a Next.js major-version downgrade, a shadcn
downgrade) or continued retrying a connectivity issue with no evidence
retrying would help. Documenting the safe path and the real
constraint is more honest than papering over either with a forced,
unreviewed change.

**Rewriting stale ADR sections instead of annotating them.** Rejected
— ADRs are historical decision records; the established, correct
pattern (used consistently since Milestone 9) is a strikethrough plus
a dated resolution note, preserving what was actually decided and when
without rewriting history to look like it was known from the start.

**Building new capabilities found "worth adding" during this
review** (e.g., actually implementing thumbnail generation while
updating its ADR entry). Rejected — explicit user instruction for this
milestone was to validate and polish, not expand scope. Real gaps
found during the audit are documented accurately, not opportunistically
built.

## Validation

- `php artisan test` — **146/146 passing**, unchanged.
- `./vendor/bin/pint --test` — clean.
- `npm run typecheck` / `npm run lint` — clean.
- `npm run test` — **20/20 passing**, unchanged.
- `npm run build` — clean; dashboard route First Load JS unchanged
  (144kB).
- `npm audit` — 7 → 6 vulnerabilities (1 genuinely fixed; the
  remaining 6 documented above, none silently ignored).
- `composer audit` — 4 advisories identified, fix path verified safe
  but not applied (network connectivity to packagist.org, documented
  above).

## Deferred Work

- **Apply the `guzzlehttp/guzzle` update** — `composer update
  guzzlehttp/guzzle --with-all-dependencies`, blocked this session by
  network connectivity to `repo.packagist.org`, not by any code or
  compatibility concern.
- **The two Next.js-internal `postcss`/`sharp` advisories** — blocked
  on an upstream Next.js release, re-check on every future Next.js
  version bump.
- **Thumbnail/responsive-image generation** — real, open, no longer
  tied to a specific past milestone; see `docs/adr/0010-media-platform.md`.
- Every other item named "Deferred Work" across
  `docs/adr/0015`–`0017` remains accurately deferred — this milestone's
  audit confirmed their status, it didn't change it.
