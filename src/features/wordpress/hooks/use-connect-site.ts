import { useMutation, useQueryClient } from "@tanstack/react-query";

import { sitesQueryKey } from "@/features/wordpress/hooks/use-sites";
import { connectSite } from "@/services/api/sites.service";

export function useConnectSite() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: connectSite,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: sitesQueryKey });
    },
  });
}
