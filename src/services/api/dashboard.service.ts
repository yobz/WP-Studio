import { apiFetch } from "@/lib/api-client";

export interface DashboardSummary {
  connected_sites: number;
  published_posts: number;
  draft_posts: number;
  storage_used_mb: number;
  storage_limit_mb: number;
  monthly_visitors: number;
  monthly_visitors_trend: number | null;
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  return apiFetch<DashboardSummary>("/api/v1/dashboard/summary");
}
