import { useDashboardOverview } from "@/features/dashboard/hooks/use-dashboard-overview";
import { mapSummaryToKpis } from "@/features/dashboard/utils/map-summary-to-kpis";

export function useKpis() {
  return useDashboardOverview((overview) => mapSummaryToKpis(overview.summary));
}
