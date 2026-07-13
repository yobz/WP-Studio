import { useQuery } from "@tanstack/react-query";

import { getAnalyticsPreview } from "@/services/mock/dashboard.service";
import type { AnalyticsRange } from "@/features/dashboard/types/dashboard.types";

export function useAnalyticsPreview(range: AnalyticsRange) {
  return useQuery({
    queryKey: ["dashboard", "analytics", range],
    queryFn: () => getAnalyticsPreview(range),
  });
}
