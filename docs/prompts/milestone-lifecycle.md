# Milestone Lifecycle

The process every milestone from M8 onward follows. Established during
the post-M7 engineering review session (2026-07-13) after the project
had accumulated seven milestones' worth of ad hoc process — this
document exists so the process is a repository artifact, not something
re-specified in a new prompt at the start of every session. Update this
file directly if the process itself needs to change; don't silently
drift from it milestone to milestone.

## The stages

1. **Architecture Review** — read `docs/AI_ENGINEERING_CONTEXT.md`'s
   reading order, the relevant ADRs, and `docs/ENGINEERING_JOURNAL.md`'s
   Future Backlog before writing anything. Explain the approach and
   trade-offs before implementing, per the project's standing
   production-mindset guidance — this is where a scope question gets
   asked, not discovered mid-implementation. Includes an **Architecture
   Drift Review** (standing since Milestone 12): before implementing,
   check for duplicate services/abstractions, overlapping
   responsibilities, ADR violations, and whether prior architectural
   decisions still hold given what this milestone is about to add.
   Either resolve any drift found or explicitly defer it with
   reasoning — never let it pass silently. Document the result (even
   "none found") in the milestone's ADR/report, the same way Milestone
   12 did on this step's first run.
2. **Implementation** — feature-first, matching `docs/CODING_STANDARDS.md`.
3. **Validation** — `typecheck`, `lint`, `build` (frontend);
   `php artisan test` (backend) if backend code changed. A UI change
   gets driven in a real browser, not just typechecked — see the
   project's own `verify` practice in prior DEVLOG entries.
4. **Self Review** — an independent pass over the milestone's own work,
   the same way Milestones 3→3.1 and 4→4.1 closed real findings before
   the next milestone started. Look for what a fresh reader would catch
   that the implementer's own momentum missed.
5. **Documentation** — update `docs/PROJECT.md` (new section for what
   shipped), `docs/DEVLOG.md` (dated entry, the *what*), and the
   relevant ADR (the *why*, alternatives considered, trade-offs) if the
   milestone made a real architectural decision.
6. **Engineering Journal** — add investigation entries for anything
   non-obvious that got debugged, update the Future Backlog (add new
   items, resolve or reprioritize existing ones), and add an Interview
   Highlights / Resume Highlights subsection for this milestone.
7. **Milestone Review** — an honest assessment against the production
   engineering layers below: what changed, what was deferred, what to
   watch for later.
8. **Technical Debt Review** — reconcile the Future Backlog against
   what actually shipped; nothing should be silently dropped.
9. **Session Handoff** — overwrite `docs/SESSION_HANDOFF.md` with
   where things stand: uncommitted work, the immediate next step, any
   live environment gotchas.
10. **Commit** — small, focused commits per `docs/CODING_STANDARDS.md`.
11. **Release Tag** — if the milestone completes a release grouping in
    `docs/ROADMAP.md`.
12. **Next Milestone** — starts only after explicit approval, per
    `docs/ROADMAP.md`'s standing rule.

Never skip a stage. A milestone that skips Self Review or the
Engineering Journal update is exactly how the M6/M7 uncommitted-work
gap and the M5 review's four findings happened — both were caught by
someone deliberately doing the review stage, not by luck.

## Production engineering layers to consider every milestone

Not every milestone touches every layer — but every milestone should
be able to say, for each layer that's relevant to what it built,
**what changed**, **what was intentionally deferred and why**, and
**what a future milestone should watch for**. This is the same
discipline the ADRs already apply to individual decisions, generalized
across the full stack:

Frontend Architecture · Backend/API · Database · Authentication ·
Authorization · Storage · Search · Background Jobs · Queues · Caching ·
CDN · Rate Limiting · Security · CI/CD · Deployment · Cloud
Infrastructure · Scalability · Load Balancing · Monitoring · Logging ·
Error Tracking · Disaster Recovery · Developer Experience

A milestone that adds a database table should say something about
Database and Security (mass assignment, validation) even if it says
nothing about CDN or Load Balancing — silence on an irrelevant layer is
fine; silence on a relevant one is a gap the next review should catch.
