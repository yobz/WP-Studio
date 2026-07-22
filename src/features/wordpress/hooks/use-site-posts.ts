import { keepPreviousData, useQuery } from "@tanstack/react-query";

import { getSitePosts } from "@/services/api/posts.service";

export function sitePostsQueryKey(siteId: number, page?: number) {
  return page === undefined
    ? (["sites", siteId, "posts"] as const)
    : (["sites", siteId, "posts", page] as const);
}

export function useSitePosts(siteId: number, page = 1) {
  return useQuery({
    queryKey: sitePostsQueryKey(siteId, page),
    queryFn: () => getSitePosts(siteId, page),
    placeholderData: keepPreviousData,
  });
}
