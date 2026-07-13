import { useQuery } from "@tanstack/react-query";

import { mapSummaryToKpis } from "@/features/dashboard/utils/map-summary-to-kpis";
import { getDashboardSummary } from "@/services/api/dashboard.service";

/**
 * The one widget this milestone wired to the real Laravel API instead
 * of the mock service layer — see
 * docs/adr/0004-backend-foundation.md. Every other dashboard hook
 * still imports from `@/services/mock/...`; this is the intentional
 * exception proving the pattern, not a partial migration left
 * mid-way. `KpiCards` itself is unchanged — it still just consumes
 * `Kpi[]` from this hook.
 */
export function useKpis() {
  return useQuery({
    queryKey: ["dashboard", "kpis"],
    queryFn: async () => mapSummaryToKpis(await getDashboardSummary()),
  });
}
