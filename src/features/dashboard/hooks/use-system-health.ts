import { useQuery } from "@tanstack/react-query";

import { mapSystemHealth } from "@/features/dashboard/utils/map-system-health";
import { getSystemHealth } from "@/services/api/system-health.service";

export function useSystemHealth() {
  return useQuery({
    queryKey: ["dashboard", "system-health"],
    queryFn: async () => mapSystemHealth(await getSystemHealth()),
  });
}
