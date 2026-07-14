import { useQuery } from "@tanstack/react-query";

import { mapSiteToWordPressOverview } from "@/features/dashboard/utils/map-site-to-wordpress-overview";
import { getSites } from "@/services/api/sites.service";

export function useWordPressOverview() {
  return useQuery({
    queryKey: ["dashboard", "wordpress-overview"],
    queryFn: async () => {
      const sites = await getSites({ status: "connected" });
      return sites.length > 0 ? mapSiteToWordPressOverview(sites[0]) : null;
    },
  });
}
