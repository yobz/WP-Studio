import { useMutation, useQueryClient } from "@tanstack/react-query";

import { sitesQueryKey } from "@/features/wordpress/hooks/use-sites";
import {
  deleteSite,
  disconnectSite,
  refreshSiteMetadata,
  verifySiteConnection,
} from "@/services/api/sites.service";

function useSitesInvalidation() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: sitesQueryKey });
}

export function useDisconnectSite() {
  const invalidate = useSitesInvalidation();

  return useMutation({
    mutationFn: disconnectSite,
    onSuccess: invalidate,
  });
}

export function useVerifyConnection() {
  const invalidate = useSitesInvalidation();

  return useMutation({
    mutationFn: verifySiteConnection,
    onSettled: invalidate,
  });
}

export function useRefreshMetadata() {
  const invalidate = useSitesInvalidation();

  return useMutation({
    mutationFn: refreshSiteMetadata,
    onSettled: invalidate,
  });
}

export function useDeleteSite() {
  const invalidate = useSitesInvalidation();

  return useMutation({
    mutationFn: deleteSite,
    onSuccess: invalidate,
  });
}
