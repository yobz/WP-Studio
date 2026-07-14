import { useQuery } from "@tanstack/react-query";

import { mapSummaryToKpis } from "@/features/dashboard/utils/map-summary-to-kpis";
import { getDashboardSummary } from "@/services/api/dashboard.service";

export function useKpis() {
  return useQuery({
    queryKey: ["dashboard", "kpis"],
    queryFn: async () => mapSummaryToKpis(await getDashboardSummary()),
  });
}
