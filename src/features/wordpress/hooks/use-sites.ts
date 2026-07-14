import { useQuery } from "@tanstack/react-query";

import { getSites } from "@/services/api/sites.service";

export const sitesQueryKey = ["sites"] as const;

export function useSites() {
  return useQuery({
    queryKey: sitesQueryKey,
    queryFn: () => getSites(),
  });
}
