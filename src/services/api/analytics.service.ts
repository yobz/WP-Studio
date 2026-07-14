import { apiFetch } from "@/lib/api-client";
import type { AnalyticsRange } from "@/features/dashboard/types/dashboard.types";

export interface ApiAnalyticsPoint {
  date: string;
  visitors: number;
  posts_published: number;
}

export async function getAnalyticsPreview(
  range: AnalyticsRange,
): Promise<ApiAnalyticsPoint[]> {
  return apiFetch<ApiAnalyticsPoint[]>(`/api/v1/analytics?range=${range}`);
}
