# WP Studio

## Overview

WP Studio is a SaaS application for managing one or multiple WordPress
websites from a single dashboard. It focuses on content management,
publishing workflows, analytics, and WordPress integrations, with
AI-assisted content generation planned for a later phase.

Built as a portfolio project demonstrating production-quality full stack
engineering across a Next.js/React frontend and a Laravel/MySQL backend.

## Stack

| Layer            | Choice                              |
| ----------------- | ------------------------------------ |
| Frontend          | Next.js 15, React 19, TypeScript     |
| Styling           | Tailwind CSS, shadcn/ui, Lucide React |
| Backend           | Laravel 12, PHP                      |
| Database          | MySQL                                |
| Client state      | Zustand, React Context API           |
| Server state       | TanStack Query                       |
| Forms/validation  | React Hook Form, Zod                 |
| Tables/charts     | TanStack Table, Recharts             |
| Testing           | Vitest, React Testing Library        |
| Deployment         | Vercel (frontend), Railway (backend) |
| CI/CD             | GitHub Actions                       |

Planned later: GraphQL, Docker, cloud deployment hardening, AI integration.

## Architecture

Feature-first organization under `src/`. Each feature in `src/features/`
owns its own `components/`, `hooks/`, `services/`, `types/`, and `utils/`.
Shared, cross-feature code lives in the top-level `components/`, `hooks/`,
`lib/`, `services/`, `store/`, `types/`, and `utils/` directories.

## Status

Milestone 1 (Project Initialization) complete. See `ROADMAP.md` for the
full milestone list and `DEVLOG.md` for a running log of completed work.
