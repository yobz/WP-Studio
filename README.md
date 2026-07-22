# WP Studio

A SaaS dashboard for managing one or more WordPress sites — content,
publishing, analytics, WordPress integrations, and AI-assisted content
generation (Anthropic Claude / Google Gemini) from a single place. Built
as a portfolio project demonstrating production-quality full stack
engineering: Next.js/React frontend, Laravel backend (SQLite locally,
PostgreSQL in production — see
[`docs/adr/0017-cloud-deployment-and-security-hardening.md`](docs/adr/0017-cloud-deployment-and-security-hardening.md)).

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

## Docker — one-command setup

Requires only [Docker Desktop](https://www.docker.com/products/docker-desktop/)
— no local PHP, Composer, or Node install.

```bash
docker compose up
```

Frontend: [http://localhost:3000](http://localhost:3000). Backend API:
[http://localhost:8000](http://localhost:8000). First run builds the
images and bootstraps `backend/.env` + the SQLite database automatically
— give it a minute. See
[`docs/adr/0013-docker-development-environment.md`](docs/adr/0013-docker-development-environment.md)
for the full architecture, every container's responsibility, and the
volume/networking trade-offs.

## Frontend — local setup (without Docker)

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

Other scripts: `npm run typecheck`, `npm run lint`, `npm run build`,
`npm run format:check`.

## Backend — local setup (without Docker)

See [`backend/README.md`](backend/README.md) — SQLite, zero external
services required.

## Status

Milestone 20 (Production Release) complete — the last milestone on
[`docs/ROADMAP.md`](docs/ROADMAP.md), closing v1.0. The app is
deployment-ready, not deployed; see
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the actual runbook and
[`docs/SESSION_HANDOFF.md`](docs/SESSION_HANDOFF.md) for exactly where
the project stands right now.
