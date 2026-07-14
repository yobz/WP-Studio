import { useMutation, useQueryClient } from "@tanstack/react-query";

import { sitesQueryKey } from "@/features/wordpress/hooks/use-sites";
import { siteQueryKey } from "@/features/wordpress/hooks/use-site";
import { syncStatusQueryKey } from "@/features/wordpress/hooks/use-sync-status";
import { syncSite } from "@/services/api/sites.service";

export function useSyncSite(siteId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => syncSite(siteId),
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: sitesQueryKey });
      queryClient.invalidateQueries({ queryKey: siteQueryKey(siteId) });
      queryClient.invalidateQueries({ queryKey: syncStatusQueryKey(siteId) });
    },
  });
}
