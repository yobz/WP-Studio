import type {
  ActivityItem,
  AnalyticsPoint,
  AnalyticsRange,
  Draft,
  SystemHealth,
} from "@/features/dashboard/types/dashboard.types";

import {
  mockActivity,
  mockAnalyticsByRange,
  mockDrafts,
  mockSystemHealth,
} from "./dashboard.mock-data";

/**
 * Mock service layer standing in for the real Laravel REST API
 * (backend/, added Milestone 6). Every function returns a Promise
 * with a simulated network delay so the calling code — TanStack
 * Query hooks in `src/features/dashboard/hooks/` — already exercises
 * real async loading/error/success handling. KPI Cards and WordPress
 * Overview are migrated off this file already (`getKpis()` and
 * `getWordPressOverview()` removed — see `src/services/api/` and
 * docs/adr/0005-domain-model.md); every other widget in this file is
 * still mock-backed and gets migrated the same way, one at a time, in
 * future milestones.
 */

const NETWORK_DELAY_MS = 600;

function delay<T>(value: T, ms = NETWORK_DELAY_MS): Promise<T> {
  return new Promise((resolve) => {
    setTimeout(() => resolve(value), ms);
  });
}

export async function getRecentActivity(): Promise<ActivityItem[]> {
  return delay(mockActivity);
}

export async function getAnalyticsPreview(
  range: AnalyticsRange,
): Promise<AnalyticsPoint[]> {
  return delay(mockAnalyticsByRange[range]);
}

export async function getSystemHealth(): Promise<SystemHealth> {
  return delay(mockSystemHealth);
}

// Deliberately fails the first two calls per browser session, then
// succeeds — a reproducible way to exercise the Recent Drafts query's
// retry strategy and Error UI on every fresh page load, rather than
// random flakiness that might never appear in a demo (or always does).
// Resets on a full page reload (module state), not on client-side
// navigation within the app.
let draftAttempts = 0;

export async function getRecentDrafts(): Promise<Draft[]> {
  draftAttempts += 1;
  if (draftAttempts <= 2) {
    await delay(null);
    throw new Error(
      "Failed to load drafts. The service is temporarily unavailable.",
    );
  }
  return delay(mockDrafts);
}
