import { useQuery } from "@tanstack/react-query";

import { getSyncStatus } from "@/services/api/sites.service";

export function syncStatusQueryKey(siteId: number) {
  return ["sites", siteId, "sync-status"] as const;
}

export function useSyncStatus(siteId: number) {
  return useQuery({
    queryKey: syncStatusQueryKey(siteId),
    queryFn: () => getSyncStatus(siteId),
    refetchInterval: (query) =>
      query.state.data?.site_status === "syncing" ? 2000 : false,
  });
}
