import { useQuery } from "@tanstack/react-query";

import { getRecentActivity } from "@/services/mock/dashboard.service";

export function useRecentActivity() {
  return useQuery({
    queryKey: ["dashboard", "activity"],
    queryFn: getRecentActivity,
  });
}
