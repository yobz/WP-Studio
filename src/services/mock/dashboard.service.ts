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
