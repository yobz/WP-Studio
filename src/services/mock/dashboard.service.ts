import type {
  ActivityItem,
  AnalyticsPoint,
  AnalyticsRange,
  Draft,
  Kpi,
  QuickAction,
  SystemHealth,
  WordPressOverview,
} from "@/features/dashboard/types/dashboard.types";

import {
  mockActivity,
  mockAnalyticsByRange,
  mockDrafts,
  mockKpis,
  mockQuickActions,
  mockSystemHealth,
  mockWordPressOverview,
} from "./dashboard.mock-data";

/**
 * Mock service layer standing in for the future Laravel REST API
 * (Milestone 7). Every function returns a Promise with a simulated
 * network delay so the calling code — TanStack Query hooks in
 * `src/features/dashboard/hooks/` — already exercises real async
 * loading/error/success handling. Swapping the mock layer for real
 * `fetch()` calls later should only require changing the function
 * bodies here, not any calling code, since the return types
 * (`src/features/dashboard/types/dashboard.types.ts`) are already
 * shaped like a plain API response.
 */

const NETWORK_DELAY_MS = 600;

function delay<T>(value: T, ms = NETWORK_DELAY_MS): Promise<T> {
  return new Promise((resolve) => {
    setTimeout(() => resolve(value), ms);
  });
}

export async function getKpis(): Promise<Kpi[]> {
  return delay(mockKpis);
}

export async function getQuickActions(): Promise<QuickAction[]> {
  return delay(mockQuickActions);
}

export async function getRecentActivity(): Promise<ActivityItem[]> {
  return delay(mockActivity);
}

export async function getWordPressOverview(): Promise<WordPressOverview> {
  return delay(mockWordPressOverview);
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
