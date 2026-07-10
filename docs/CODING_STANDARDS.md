# Coding Standards

## TypeScript

- Always use TypeScript. Never use `any`.
- Prefer explicit, narrow types over broad ones.

## Components

- Prefer Server Components; use Client Components (`"use client"`) only
  when interactivity, browser APIs, or client state require it.
- Keep components focused and single-purpose.
- Prefer composition over inheritance.
- Prefer reusable components; avoid duplicating markup or logic.
- Keep files under ~300 lines where practical — extract business logic
  into hooks (`hooks/`) or services (`services/`).

## Organization

- Feature-first: code belonging to a feature lives under
  `src/features/<feature>/`, not in the shared top-level folders.
- Shared/reusable code (used by 2+ features) lives in the top-level
  `src/components/`, `src/hooks/`, `src/lib/`, `src/services/`,
  `src/store/`, `src/types/`, `src/utils/`.

## General

- Follow SOLID principles where they add clarity, not ceremony.
- Prefer readable code over clever code.
- No premature abstraction — do not build for hypothetical future needs.
- No dead code, no commented-out code, no unused exports.

## Git / GitHub Flow

- Small, focused commits with clear messages.
- Feature branches off `main`, merged via PR.
- Every milestone should leave the app in a deployable state.
