# AI Engineering Context

The front door for any new working session — human or AI — on WP
Studio. Read this first; it doesn't repeat what's already documented
elsewhere, it tells you where to find it and in what order. The
repository is the project's memory, not conversation history: no
session should need to be told this project's history from scratch,
because it's all here.

## What this project is

A SaaS dashboard for managing WordPress sites — Next.js/React frontend,
Laravel/MySQL-candidate backend, built as a portfolio-grade production
system. One paragraph is deliberately all this document says about
that — see `docs/PROJECT.md` for the real answer.

## Reading order for a new session

1. **`docs/SESSION_HANDOFF.md`** — where things stand *right now*:
   uncommitted work, the immediate next step, any live environment
   gotchas. Overwritten each session; if it disagrees with anything
   below, trust this file, then verify against the repo itself.
2. **`docs/ROADMAP.md`** — which milestone is next and how it fits into
   the release grouping.
3. **`docs/PROJECT.md`** — current architecture and stack, told through
   what each milestone added. Its `Status` and `Known Limitations`
   sections (bottom of the file) are the closest thing to a single
   current-state snapshot.
4. **`docs/CODING_STANDARDS.md`** — the rules, short and non-negotiable.
5. **`docs/adr/`** — *why* the architecture is shaped the way it is, one
   record per significant decision, including rejected alternatives.
   Read the ones relevant to what you're about to touch.
6. **`docs/ENGINEERING_JOURNAL.md`** — investigation write-ups for
   non-obvious problems, plus two permanently-maintained sections worth
   checking before starting any new work: **Future Backlog** (known
   debt, prioritized, with the reasoning for why each item wasn't
   fixed yet) and **Interview/Resume Highlights**.
7. **`docs/DEVLOG.md`** — chronological "what shipped," one entry per
   milestone. The changelog; not where reasoning lives.
8. **`docs/MILESTONE_REPORT_M*.md`** — historical independent review
   reports (Milestones 2–5). Permanent artifacts, left in place and
   never rewritten — later milestones amend them in place with a
   resolution note rather than editing their original findings, and
   DEVLOG/Journal entries reference them by filename. They stop at M5
   because the brief that produced them changed after that point, not
   because anything is missing.

## The milestone lifecycle

Every milestone from M8 onward follows the same process:
`docs/prompts/milestone-lifecycle.md`. It exists so this doesn't need
to be re-specified at the start of every session — read it once,
follow it every time, update it if the process itself needs to change.

## Doc map — one responsibility each

| File | Owns | Does not own |
| --- | --- | --- |
| `PROJECT.md` | Current architecture/stack, told incrementally | Decision reasoning (→ ADRs), chronology (→ DEVLOG) |
| `ROADMAP.md` | Milestone list and release grouping | Milestone detail (→ DEVLOG, ADRs) |
| `DEVLOG.md` | Append-only "what shipped," dated | Reasoning, trade-offs (→ ADRs, Journal) |
| `ENGINEERING_JOURNAL.md` | Investigation narratives, Future Backlog, Interview/Resume Highlights | Accepted architecture (→ ADRs), current state (→ PROJECT.md) |
| `adr/000N-*.md` | One accepted architectural decision each, with rejected alternatives | Anything not yet decided |
| `MILESTONE_REPORT_M*.md` | Historical independent review, frozen at time of writing | Ongoing status |
| `SESSION_HANDOFF.md` | Where the *next* session should resume, overwritten each time | Anything permanent (that belongs in DEVLOG/Journal instead) |
| `CODING_STANDARDS.md` | The rules | Rationale for the rules (→ ADRs when non-obvious) |

**Deliberately not created:** a separate `ARCHITECTURE.md`
(`PROJECT.md` already owns this), `RELEASE_NOTES.md` (`DEVLOG.md`
already owns this), `ENGINEERING_DEBT.md` (the Journal's Future
Backlog already owns this), or `CURRENT_STATE.md` (`PROJECT.md`'s
`Status`/`Known Limitations` sections already own this). Adding any of
these would split one responsibility across two files with no
maintainability gain — check this table before proposing a new
top-level doc.

## Standing environment notes

- This project is developed from a **OneDrive-synced path**
  (`OneDrive\Desktop\wp-studio`). Framework build/cache directories
  (`.next/`, `backend/bootstrap/cache/`) have twice become OneDrive
  reparse-point placeholders and failed writability checks that plain
  shell commands don't reveal. Fix: `rm -rf <cache-dir>` and let the
  framework recreate it. Full detail:
  `docs/ENGINEERING_JOURNAL.md`, 2026-07-13 entry.
- This shadcn setup uses **Base UI, not Radix** — composition uses a
  `render` prop, not `asChild`; running `npx shadcn add <x>` against an
  already-customized file will silently overwrite it (`--dry-run`
  first, always). Detail: `docs/ENGINEERING_JOURNAL.md`, 2026-07-10
  entries; standing rule in `docs/adr/0001-design-system.md`.
