import { useQuery } from "@tanstack/react-query";

import { getMedia, type MediaListFilters } from "@/services/api/media.service";

export const mediaQueryKey = (filters: MediaListFilters = {}) =>
  ["media", filters] as const;

export function useMedia(filters: MediaListFilters = {}) {
  return useQuery({
    queryKey: mediaQueryKey(filters),
    queryFn: () => getMedia(filters),
  });
}
