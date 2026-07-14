import type { ApiAnalyticsPoint } from "@/services/api/analytics.service";
import type { AnalyticsPoint } from "@/features/dashboard/types/dashboard.types";

export function mapAnalyticsPoint(point: ApiAnalyticsPoint): AnalyticsPoint {
  return {
    date: point.date,
    visitors: point.visitors,
    postsPublished: point.posts_published,
  };
}
