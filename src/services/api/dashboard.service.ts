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

export interface ApiActivityItem {
  id: string;
  type: "post-published" | "draft-created" | "site-connected";
  title: string;
  site_name: string;
  timestamp: string;
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  return apiFetch<DashboardSummary>("/api/v1/dashboard/summary");
}

export async function getRecentActivity(): Promise<ApiActivityItem[]> {
  return apiFetch<ApiActivityItem[]>("/api/v1/dashboard/activity");
}
