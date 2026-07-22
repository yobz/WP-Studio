# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-20 — End of Milestone 17 (Performance & Scalability)

**Milestone state.** Milestone 17 is implemented and validated —
`docs/adr/0015-performance-and-scalability.md` has the full reasoning.
**Not yet committed or pushed** — waiting on explicit approval per this
project's standing rule (never commit without it). `docs/ROADMAP.md`,
`docs/PROJECT.md`, and `docs/DEVLOG.md` are already updated to reflect
it as complete; `docs/MILESTONE_REPORT_M17.md` has the full independent
review.

**New: Posts pagination, a fixed sync N+1, and a lighter dashboard.**
Everything measured against a temporarily inflated dataset (34 sites,
6,012 posts, 2,756 snapshots) before anything was changed — the real
dev database has since been restored to its normal demo state.
`GET /api/v1/posts` now takes `page`/`per_page` (default 50, max 100)
and returns a `meta.pagination` block. `WordPressPostMapper::upsert()`
no longer runs one lookup query per WordPress item — see gotcha #1.
`/dashboard`'s First Load JS dropped 249kB → 144kB by code-splitting
`recharts` out of the initial bundle. Redis was evaluated against real
query timings and deliberately **not** wired in — see
`docs/adr/0015-performance-and-scalability.md`'s Redis section before
reconsidering that.

**Four things worth knowing before touching this again.**

1. **`ContentTypeMapper` gained a new required method,
   `preloadExisting()`.** Any future second implementation (a Pages or
   Media mapper) must implement it too — it's what
   `ContentSyncService::sync()` calls once per page, before the
   `foreach`, to batch-load existing rows instead of querying per item.
   `WordPressPostMapper` is still the only implementation today.
2. **`apiFetch` and `apiFetchWithMeta` are both real now**
   (`src/lib/api-client.ts`). `apiFetch` (unchanged) returns just
   `data`; `apiFetchWithMeta` also returns the envelope's `meta` —
   needed by `posts.service.ts` for pagination. Use `apiFetchWithMeta`
   only where `meta` is actually consumed; everything else should stay
   on plain `apiFetch`.
3. **`sitePostsQueryKey(siteId, page?)` — the `page` argument is
   optional on purpose.** Called with one argument (from
   `site-detail.tsx`, after a sync completes) it returns the key
   *prefix*, which `invalidateQueries` matches against every page's
   cached query at once. Called with two arguments (from
   `use-site-posts.ts` itself) it returns one specific page's exact
   key. Dropping the optional form would silently break "refresh posts
   after sync" for every page except whichever one happens to be open.
4. **A stack trace pointing entirely inside a dependency's own bundled
   internals, right after a `node_modules` change, is this project's
   now-recurring "stale `.next`/`bootstrap/cache` build cache" pattern**
   (Milestones 6, 13, 15, 16 — and this session's dev-server restart hit
   a new shape of it too, an `EINVAL: readlink` error from Next's
   diagnostics writer). Delete `.next/` before investigating the error
   itself. See `docs/ENGINEERING_JOURNAL.md`.

**Immediate next step.** Milestone 18 (Observability) is next per
`docs/ROADMAP.md` — structured logging, health checks, Sentry/
OpenTelemetry, request tracing, operational metrics — but is
**explicitly not started**, waiting for approval per the milestone
lifecycle's standing rule. Milestone 17 itself also still needs
explicit commit/push approval before anything else touches this repo.

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
  since Milestone 11, sharpened in Milestone 15. This session also
  confirmed: a *full-page* `page.goto()` reload is meaningfully slower
  than client-side `Link` navigation, since the auth guard re-runs its
  session check from scratch — give it 3–4s, not 1–1.5s, when scripting
  reload-based navigation.
- `php artisan serve`'s first one or two requests after a cold start
  can be slow enough to drop a connection or return a truncated/non-
  JSON body (surfaced this session as a stray browser-console
  `SyntaxError: Invalid or unexpected token` on the very first login
  attempt against a freshly started server, gone on retry). Not an app
  bug — give the dev server a moment to warm up before the first real
  request when scripting live verification.
- `axe-core` is a real transitive dependency, never delete it during
  cleanup. `playwright` is installed with `--no-save` and uninstalled
  again after ad hoc live verification, every time.
- Never print any part of an API key/credential into tool output or
  logs.
- Demo login: `test@example.com` / `password`.

**Validation status as of this session.** Backend: `php artisan test`
— **142/142 passing** (unchanged). `./vendor/bin/pint --test`
(full-repo) — clean. Frontend: `npm run test` — **20/20 passing**
(unchanged). `typecheck`/`lint`/`build` all clean;
`/dashboard` First Load JS confirmed at 144kB (down from 249kB) via the
build's own route-size output. Live verification: real login →
dashboard (chart lazy-loads and renders, Recent Drafts capped at 5) →
a real site's posts page (`/wordpress/1/posts`, all 8 posts rendering
correctly) — via Playwright against the restored, non-inflated dev
database. Re-ran the original N+1 profiling script after the fix to
confirm 300 → 201 queries empirically, not just by reading the diff.
