import { useQuery } from "@tanstack/react-query";

import { graphqlFetch } from "@/lib/graphql-client";
import type {
  AnalyticsPoint,
  AnalyticsRange,
} from "@/features/dashboard/types/dashboard.types";

const RANGE_TO_ENUM: Record<AnalyticsRange, string> = {
  "7d": "SEVEN_D",
  "30d": "THIRTY_D",
  "90d": "NINETY_D",
};

const ANALYTICS_PREVIEW_QUERY = /* GraphQL */ `
  query AnalyticsPreview($range: AnalyticsRange!) {
    analyticsPreview(range: $range) {
      date
      visitors
      postsPublished
    }
  }
`;

export function useAnalyticsPreview(range: AnalyticsRange) {
  return useQuery({
    queryKey: ["dashboard", "analytics", range],
    queryFn: () =>
      graphqlFetch<{ analyticsPreview: AnalyticsPoint[] }>(
        ANALYTICS_PREVIEW_QUERY,
        { range: RANGE_TO_ENUM[range] },
      ).then((result) => result.analyticsPreview),
  });
}
