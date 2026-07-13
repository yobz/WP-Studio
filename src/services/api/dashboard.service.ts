import { apiFetch } from "@/lib/api-client";

/**
 * Mirrors backend/app/Http/Resources/V1/DashboardSummaryResource.php.
 * Deliberately raw/numeric, same reasoning as the backend DTO it
 * comes from — display formatting happens in
 * map-summary-to-kpis.ts, not here.
 */
export interface DashboardSummary {
  connected_sites: number;
  published_posts: number;
  draft_posts: number;
  storage_used_mb: number;
  storage_limit_mb: number;
  monthly_visitors: number;
  // Percentage change vs. the prior 14-day window; null when there's
  // no prior-period AnalyticsSnapshot history to compare against.
  monthly_visitors_trend: number | null;
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  return apiFetch<DashboardSummary>("/api/v1/dashboard/summary");
}
