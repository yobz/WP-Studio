import { useQuery } from "@tanstack/react-query";

import { getSitePosts } from "@/services/api/posts.service";

export function sitePostsQueryKey(siteId: number) {
  return ["sites", siteId, "posts"] as const;
}

export function useSitePosts(siteId: number) {
  return useQuery({
    queryKey: sitePostsQueryKey(siteId),
    queryFn: () => getSitePosts(siteId),
  });
}
