import { useDashboardOverview } from "@/features/dashboard/hooks/use-dashboard-overview";
import { mapSystemHealth } from "@/features/dashboard/utils/map-system-health";

export function useSystemHealth() {
  return useDashboardOverview((overview) =>
    mapSystemHealth(overview.systemHealth),
  );
}
