import { useQuery } from "@tanstack/react-query";

import { mapAnalyticsPoint } from "@/features/dashboard/utils/map-analytics-points";
import { getAnalyticsPreview } from "@/services/api/analytics.service";
import type { AnalyticsRange } from "@/features/dashboard/types/dashboard.types";

export function useAnalyticsPreview(range: AnalyticsRange) {
  return useQuery({
    queryKey: ["dashboard", "analytics", range],
    queryFn: async () =>
      (await getAnalyticsPreview(range)).map(mapAnalyticsPoint),
  });
}
