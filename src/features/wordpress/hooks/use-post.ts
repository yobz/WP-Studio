import { useQuery } from "@tanstack/react-query";

import { getPost } from "@/services/api/posts.service";

export function postQueryKey(id: number) {
  return ["posts", id] as const;
}

export function usePost(id: number) {
  return useQuery({
    queryKey: postQueryKey(id),
    queryFn: () => getPost(id),
  });
}
