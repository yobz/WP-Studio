import { useQuery } from "@tanstack/react-query";

import { getSite } from "@/services/api/sites.service";

export function siteQueryKey(id: number) {
  return ["sites", id] as const;
}

export function useSite(id: number) {
  return useQuery({
    queryKey: siteQueryKey(id),
    queryFn: () => getSite(id),
    refetchInterval: (query) =>
      query.state.data?.status === "syncing" ? 2000 : false,
  });
}
