import { useQuery } from "@tanstack/react-query";

import { getAiJob } from "@/services/api/ai.service";

export function aiJobQueryKey(id: number) {
  return ["ai", "jobs", id] as const;
}

export function useAiJob(id: number | null) {
  return useQuery({
    queryKey: aiJobQueryKey(id ?? 0),
    queryFn: () => getAiJob(id as number),
    enabled: id !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === "pending" || status === "processing" ? 2000 : false;
    },
  });
}
