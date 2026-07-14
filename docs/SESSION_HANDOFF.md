# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-15 — End of Milestone 12 (Media Platform & Storage)

**Milestone state.** Milestone 12 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M12.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. **Not yet
committed** — this milestone's own brief requires stopping here for
approval before starting Milestone 13.

**Milestones 8 through 11 are already committed and pushed** (from
earlier sessions). `git status` at the start of this milestone's work
was clean; every file changed since is Milestone 12's own work.

**New local setup requirement: the public storage symlink.** Media
served from the `public` disk needs `php artisan storage:link` run
once (creates `backend/public/storage` → `backend/storage/app/public`).
This session ran it — the symlink now exists in this environment — but
a fresh clone/environment will need it run again before uploaded/
synced images resolve to a working URL instead of a 404.

**Two schema-level lessons from this milestone, worth remembering
before adding a unique constraint to any `SoftDeletes` table.** A
DB-level unique index has no concept of `deleted_at` — a soft-deleted
row still physically occupies its slot in the constraint. This
milestone added, then removed, two unique constraints on the `media`
table for exactly this reason (see
`docs/ENGINEERING_JOURNAL.md`'s 2026-07-15 entry), and discovered
`posts`' own `(site_id, wordpress_post_id)` unique index carries the
identical, apparently-unexercised risk. Not fixed on `posts` this
session (out of scope, not currently reachable) — named in
`docs/PROJECT.md`'s Known Limitations for a future session's
attention.

**A route-naming gotcha, worth knowing before adding another
`Route::apiResource()` for a resource whose plural is unusual.**
`Route::apiResource('media', MediaController::class)` auto-generates
`{medium}` as its URI parameter (English "media" is already the
plural of "medium") — silently breaking implicit route-model-binding
against a controller written with `Media $media`, with no thrown
error (a blank, unhydrated model instead). Fixed here with
`->parameters(['media' => 'media'])`. Worth checking
`php artisan route:list --path=<resource>` after adding any new
`apiResource()` route for a resource name that might not pluralize/
singularize the way it looks like it should.

**Immediate next step.** Milestone 13 (GraphQL Layer, per
`docs/ROADMAP.md`) is next in sequence — but is **explicitly not
started**, waiting for approval per the milestone lifecycle's standing
rule. This milestone's own report suggested Media is now available for
any feature needing file storage (e.g. a future Publishing milestone
attaching media to outbound WordPress writes) as an alternative
consideration, but did not recommend reordering the roadmap.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8, and the same "expect two or three `php.exe` processes"
  note from Milestone 11 once a queue worker is also running — check
  `tasklist /FI "IMAGENAME eq php.exe" /V` before assuming something
  is stuck.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev`.
- **Confirmed to recur, not just a one-off:** Next.js client-side (App
  Router) navigation with Playwright needs `page.goto()` or a manual
  URL-polling helper, not a `locator.click()` + `page.waitForURL()`
  combination — this bit again during this milestone's own
  verification despite being documented since Milestone 11. See
  `docs/ENGINEERING_JOURNAL.md`'s 2026-07-15 recurrence entry — this is
  now a permanent journal entry, not just a session-snapshot note, so
  it survives past this file being overwritten.
- When stopping ad hoc dev servers/workers started during
  verification, identify each one's **specific PID** (`netstat -ano |
  grep LISTENING` for web servers; `tasklist /FI "IMAGENAME eq
  php.exe" /V` for the queue worker, which doesn't hold a listening
  port) and kill only those PIDs. Never `taskkill //IM php.exe` or
  similar — it terminates every process with that image name
  system-wide. This project's own auto-mode classifier will correctly
  deny a broad by-image-name kill; treat that denial as correct
  behavior, not an obstacle.
- `axe-core` is a real transitive dependency (`eslint-plugin-jsx-a11y`
  needs it), not just ad hoc verification tooling — never delete it
  during cleanup. This session's verification installed only
  `playwright` temporarily and left `axe-core` untouched throughout.
- Local WordPress connection/sync testing: there is no real WordPress
  server in this environment. `DemoDataSeeder`'s seeded sites carry
  dummy credentials and fake `.example.com` URLs specifically so
  connection/sync/media-download actions against seeded data fail
  gracefully rather than silently looking like they work — expected,
  not a bug. This milestone's WordPress featured-image download path
  is verified by the real Pest suite (genuine `Http::fake()` responses,
  genuine disk writes) rather than a live browser demo against seeded
  data, for the same reason every prior milestone's WordPress testing
  has worked within this constraint.
- Demo login: `test@example.com` / `password`
  (`backend/database/seeders/DatabaseSeeder.php` + `UserFactory`'s
  default).

**Validation status as of this session.** Backend: `php artisan test`
— **120/120 passing** (up from 103). `./vendor/bin/pint --dirty`:
clean. Frontend: `typecheck`, `lint`, `build` all pass, including the
new `/media` route. Live verification with a real backend (not a
mock): uploaded a real file, saw it in the grid, opened the preview
dialog, edited and saved alt text, switched to list view, deleted it,
confirmed the library returned to its empty state — zero console
errors throughout. `axe-core`: zero violations across the Media
Library, the preview dialog, the Dashboard, the posts list, and post
detail — including a real WCAG AA contrast defect (a destructive
button on a dialog's muted footer background) found and fixed during
this same verification pass, not before it. See
`docs/MILESTONE_REPORT_M12.md`.
