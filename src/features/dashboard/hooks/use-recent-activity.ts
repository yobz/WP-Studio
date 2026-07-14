import { useQuery } from "@tanstack/react-query";

import { mapActivityItem } from "@/features/dashboard/utils/map-activity";
import { getRecentActivity } from "@/services/api/dashboard.service";

export function useRecentActivity() {
  return useQuery({
    queryKey: ["dashboard", "activity"],
    queryFn: async () => (await getRecentActivity()).map(mapActivityItem),
  });
}
