import { useQuery } from "@tanstack/react-query";

import { getSystemHealth } from "@/services/mock/dashboard.service";

export function useSystemHealth() {
  return useQuery({
    queryKey: ["dashboard", "system-health"],
    queryFn: getSystemHealth,
  });
}
