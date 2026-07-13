import { useQuery } from "@tanstack/react-query";

import { mapSiteToWordPressOverview } from "@/features/dashboard/utils/map-site-to-wordpress-overview";
import { getSites } from "@/services/api/sites.service";

/**
 * The second widget migrated off the mock service layer (Milestone 7,
 * after KPI Cards in Milestone 6) — see
 * docs/adr/0005-domain-model.md. Shows the workspace's first connected
 * site; there's no "current workspace" to scope to yet (no auth), so
 * this reads across all sites, same as the Dashboard summary. Returns
 * `null`, not an empty object, when no connected site exists —
 * `WordPressOverview` (the widget) renders a real Empty state for
 * that case, which the mock layer never needed since its fixture data
 * always had exactly one site.
 */
export function useWordPressOverview() {
  return useQuery({
    queryKey: ["dashboard", "wordpress-overview"],
    queryFn: async () => {
      const sites = await getSites({ status: "connected" });
      return sites.length > 0 ? mapSiteToWordPressOverview(sites[0]) : null;
    },
  });
}
