import { useQuery } from "@tanstack/react-query";

import { getKpis } from "@/services/mock/dashboard.service";

export function useKpis() {
  return useQuery({
    queryKey: ["dashboard", "kpis"],
    queryFn: getKpis,
  });
}
