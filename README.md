# WP Studio

A SaaS dashboard for managing one or more WordPress sites — content,
publishing, analytics, and WordPress integrations from a single place,
with AI-assisted content generation planned for a later phase. Built as
a portfolio project demonstrating production-quality full stack
engineering: Next.js/React frontend, Laravel/MySQL-candidate backend.

Full architecture, stack, and current status: **[`docs/PROJECT.md`](docs/PROJECT.md)**.
Starting a new working session? Read **[`docs/AI_ENGINEERING_CONTEXT.md`](docs/AI_ENGINEERING_CONTEXT.md)**
first — it's the reading order for everything else in `docs/`.

## Structure

- `src/` — Next.js 15 / React 19 / TypeScript frontend, feature-first
  under `src/features/`. See [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md).
- `backend/` — Laravel 12 API, entirely self-contained. See
  [`backend/README.md`](backend/README.md) for local setup.
- `docs/` — architecture decisions (`docs/adr/`), the milestone
  roadmap, and every other project record. The repository is this
  project's memory — see `docs/AI_ENGINEERING_CONTEXT.md`.

## Frontend — local setup

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000). The dashboard
renders against a mix of real API data and a mock service layer until
every widget is migrated — see `docs/PROJECT.md`'s Known Limitations.

Other scripts: `npm run typecheck`, `npm run lint`, `npm run build`,
`npm run format:check`.

## Backend — local setup

See [`backend/README.md`](backend/README.md) — SQLite, zero external
services required.

## Status

Milestone 7 (Domain & Data Platform) complete. See
[`docs/ROADMAP.md`](docs/ROADMAP.md) for what's next and
[`docs/SESSION_HANDOFF.md`](docs/SESSION_HANDOFF.md) for exactly where
the project stands right now.
