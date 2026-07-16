import { useDashboardOverview } from "@/features/dashboard/hooks/use-dashboard-overview";

export function useRecentActivity() {
  return useDashboardOverview((overview) => overview.recentActivity);
}
