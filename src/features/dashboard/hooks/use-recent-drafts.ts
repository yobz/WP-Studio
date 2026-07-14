import { useQuery } from "@tanstack/react-query";

import { mapPostToDraft } from "@/features/dashboard/utils/map-posts-to-drafts";
import { getRecentDrafts } from "@/services/api/posts.service";

export function useRecentDrafts() {
  return useQuery({
    queryKey: ["dashboard", "drafts"],
    queryFn: async () => (await getRecentDrafts()).map(mapPostToDraft),
  });
}
