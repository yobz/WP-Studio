# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-14 — End of Milestone 9 (WordPress Integration Platform)

**Milestone state.** Milestone 9 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M9.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. **Not yet
committed** (see below) and **Milestone 10 has not started** — this
milestone's own brief requires stopping here for approval before
continuing.

**Uncommitted work — two milestones deep this time.** `git log` stops
at "Modernize engineering docs and refine roadmap through M20" —
Milestones 6, 7, and the post-M7 documentation session are all already
committed cleanly. **Milestones 8 (Authentication & Authorization) and
9 (WordPress Integration Platform) were both implemented in this same
continuous session and are both still uncommitted.** `git status`
confirms the working tree is exactly M8 + M9 — nothing else mixed in.
Recommended next action: split into two commits matching the
established one-commit-per-milestone precedent (M6+M7 and the docs
session were each committed separately) — M8 first, M9 second, since
M9's diff genuinely builds on M8's (the `sites` migration additions,
`SiteController` changes, etc. are new in M9, not overlapping edits to
M8 files) — then push. Happy to do this split on request; wasn't done
automatically since this milestone's own brief only asked to stop and
wait for approval, not to commit.

**Immediate next step.** Milestone 10 (API Completion & Frontend
Migration) is next per `docs/ROADMAP.md`'s v0.8 release, but is
**explicitly not started**.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted after
  Milestone 8 — check `netstat -ano | grep :8000` for stray `php`
  processes if requests start hanging.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev` — Fast Refresh
  interfered with client-side navigation during Milestone 8's own
  verification (see `docs/ENGINEERING_JOURNAL.md`).
- This environment has real outbound internet access (confirmed this
  session) — Milestone 9's own browser verification used a real
  request to `example.com` to prove the WordPress integration's error
  handling against a genuinely reachable, non-WordPress site. Don't
  assume a sandboxed/offline environment if a future session needs to
  verify similar external-call behavior.
- Local WordPress connection testing: there is no real WordPress
  server to connect to in this environment. `DemoDataSeeder`'s seeded
  sites carry a dummy, non-working credential (`demo demo demo demo
  demo demo`) specifically so "Verify Connection" against seeded data
  fails gracefully rather than silently looking like it works — this
  is expected, not a bug, if you see it while exploring the app
  locally.

**Validation status as of this session.** Frontend: `typecheck`,
`lint`, `build` all pass. Backend: `php artisan test` — 73/73 passing
(up from 57 after Milestone 8). Full connect/verify/SSRF-rejection
flow verified end-to-end in a real, production-mode browser with real
network calls (not mocked) — see `docs/MILESTONE_REPORT_M9.md`.
