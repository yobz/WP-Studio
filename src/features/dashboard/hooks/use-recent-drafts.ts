import { useQuery } from "@tanstack/react-query";

import { getRecentDrafts } from "@/services/mock/dashboard.service";

export function useRecentDrafts() {
  return useQuery({
    queryKey: ["dashboard", "drafts"],
    queryFn: getRecentDrafts,
    retry: 1,
  });
}
