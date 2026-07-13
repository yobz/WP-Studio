import { useQuery } from "@tanstack/react-query";

import { getWordPressOverview } from "@/services/mock/dashboard.service";

export function useWordPressOverview() {
  return useQuery({
    queryKey: ["dashboard", "wordpress-overview"],
    queryFn: getWordPressOverview,
  });
}
