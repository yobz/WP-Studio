import { useQuery } from "@tanstack/react-query";

import { getRecentDrafts } from "@/services/mock/dashboard.service";

export function useRecentDrafts() {
  return useQuery({
    queryKey: ["dashboard", "drafts"],
    queryFn: getRecentDrafts,
    // Overrides the global default (2 retries) — the mock service fails
    // exactly twice per session, so a single retry means both automatic
    // attempts are exhausted and the Error UI actually renders (rather
    // than silently recovering before the user ever sees it). The
    // component's manual "Try again" then becomes the 3rd attempt, which
    // succeeds. See src/services/mock/dashboard.service.ts.
    retry: 1,
  });
}
