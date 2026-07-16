import { useQuery } from "@tanstack/react-query";

import { graphqlFetch } from "@/lib/graphql-client";
import type { ActivityType } from "@/features/dashboard/types/dashboard.types";

export interface GraphQLDashboardSummary {
  connectedSites: number;
  publishedPosts: number;
  draftPosts: number;
  storageUsedMb: number;
  storageLimitMb: number;
  monthlyVisitors: number;
  monthlyVisitorsTrend: number | null;
}

// GraphQL enum fields serialize as their schema NAME (e.g. "POST_PUBLISHED"),
// not the @enum(value: ...) internal value the backend's DTO actually uses —
// translated back to the internal value below so every existing component
// (keyed on "post-published" etc since Milestone 5) needs no changes.
type WireActivityType = "POST_PUBLISHED" | "DRAFT_CREATED" | "SITE_CONNECTED";

const ACTIVITY_TYPE_FROM_WIRE: Record<WireActivityType, ActivityType> = {
  POST_PUBLISHED: "post-published",
  DRAFT_CREATED: "draft-created",
  SITE_CONNECTED: "site-connected",
};

interface WireActivityItem {
  id: string;
  type: WireActivityType;
  title: string;
  siteName: string;
  timestamp: string;
}

export interface GraphQLActivityItem {
  id: string;
  type: ActivityType;
  title: string;
  siteName: string;
  timestamp: string;
}

export interface GraphQLSystemHealth {
  apiStatus: string;
  wordpressConnection: string;
  storageUsedPercent: number;
  queueDriver: string;
  queuePending: number | null;
  queueFailed: number;
  queueOldestPendingSeconds: number | null;
  queueStatus: string;
}

export interface DashboardOverview {
  summary: GraphQLDashboardSummary;
  recentActivity: GraphQLActivityItem[];
  systemHealth: GraphQLSystemHealth;
}

interface WireDashboardOverview {
  summary: GraphQLDashboardSummary;
  recentActivity: WireActivityItem[];
  systemHealth: GraphQLSystemHealth;
}

const DASHBOARD_OVERVIEW_QUERY = /* GraphQL */ `
  query DashboardOverview {
    dashboardOverview {
      summary {
        connectedSites
        publishedPosts
        draftPosts
        storageUsedMb
        storageLimitMb
        monthlyVisitors
        monthlyVisitorsTrend
      }
      recentActivity {
        id
        type
        title
        siteName
        timestamp
      }
      systemHealth {
        apiStatus
        wordpressConnection
        storageUsedPercent
        queueDriver
        queuePending
        queueFailed
        queueOldestPendingSeconds
        queueStatus
      }
    }
  }
`;

export const dashboardOverviewQueryKey = ["dashboard", "overview"] as const;

/**
 * One GraphQL request backing the KPI, Recent Activity, and System
 * Health widgets — replacing three separate REST round-trips. `select`
 * lets each widget-specific hook (useKpis, useRecentActivity,
 * useSystemHealth) derive its own shape from the same shared cache
 * entry without duplicating the network request.
 */
export function useDashboardOverview<T = DashboardOverview>(
  select?: (data: DashboardOverview) => T,
) {
  return useQuery({
    queryKey: dashboardOverviewQueryKey,
    queryFn: () =>
      graphqlFetch<{ dashboardOverview: WireDashboardOverview }>(
        DASHBOARD_OVERVIEW_QUERY,
      ).then((result): DashboardOverview => ({
        ...result.dashboardOverview,
        recentActivity: result.dashboardOverview.recentActivity.map((item) => ({
          ...item,
          type: ACTIVITY_TYPE_FROM_WIRE[item.type],
        })),
      })),
    select,
  });
}
