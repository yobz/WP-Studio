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

## Testing

- Vitest + React Testing Library (Milestone 16). Test files sit next to
  the code they cover (`foo.ts` → `foo.test.ts`), not in a parallel
  `__tests__` tree.
- Cover critical flows (forms, stateful widgets, hooks with real logic)
  and pure logic (mappers, utilities) — not every component. A
  presentational component with no branching logic doesn't need a test
  of its own.
- Test through the public interface (render, interact, assert on what
  the user sees) — mock only at the actual network boundary (a
  `services/api/*.service.ts` function), never React Query or the
  component tree itself.
- `npm run test` runs once (CI); `npm run test:watch` for local
  development.

## General

- Follow SOLID principles where they add clarity, not ceremony.
- Prefer readable code over clever code.
- No premature abstraction — do not build for hypothetical future needs.
- No dead code, no commented-out code, no unused exports.

## Git / GitHub Flow

- Small, focused commits with clear messages.
- Feature branches off `master`, merged via PR. CI (GitHub Actions,
  Milestone 16) runs lint/typecheck/tests/build on every PR and on
  every push to `master`.
- Every milestone should leave the app in a deployable state.
