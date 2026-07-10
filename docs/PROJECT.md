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
| Styling           | Tailwind CSS 4, shadcn/ui (Base UI primitives), Lucide React |
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

## Theming

Design tokens live in `src/app/globals.css` as CSS custom properties,
bridged into Tailwind's theme via `@theme inline` (Tailwind v4's
CSS-first config — there is no `tailwind.config.ts`). Tokens cover
color (including `success`/`warning` alongside shadcn's standard
`destructive`), radius, shadows, and transition duration. Dark mode is
class-based (`.dark` on `<html>`), not just `prefers-color-scheme`, so
a manual theme toggle can be added later. Base color palette is
neutral grayscale; `shadcn/ui` is configured with Base UI (not Radix)
as its primitive library, per the CLI's current default.

Typography: Geist (primary) with Inter as an explicit fallback, both
self-hosted via `next/font/google` for zero layout shift.

## Status

Milestone 2 (Project Foundation) complete. See `ROADMAP.md` for the
full milestone list and `DEVLOG.md` for a running log of completed work.
