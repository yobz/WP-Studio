/**
 * Dashboard domain types. Deliberately plain data — no icon/component
 * references — so these shapes can be satisfied by a real Laravel REST
 * response later without changing anything in this file. Presentation
 * concerns (which icon represents which KPI, etc.) are mapped in the
 * component layer, not here.
 */

export interface Kpi {
  id:
    | "connected-sites"
    | "published-posts"
    | "draft-posts"
    | "monthly-visitors"
    | "storage-usage";
  label: string;
  value: string;
  trend?: {
    value: string;
    direction: "up" | "down" | "neutral";
  };
}

export interface QuickAction {
  id: "new-post" | "generate-ai-draft" | "connect-site" | "view-analytics";
  title: string;
  description: string;
}

export type ActivityType =
  "post-published" | "draft-created" | "plugin-updated" | "ai-draft-generated";

export interface ActivityItem {
  id: string;
  type: ActivityType;
  title: string;
  siteName: string;
  timestamp: string;
}

export type SiteStatus = "connected" | "syncing" | "disconnected";

export interface WordPressOverview {
  siteName: string;
  status: SiteStatus;
  wordpressVersion: string;
  theme: string;
  pluginUpdatesAvailable: number;
}

export interface AnalyticsPoint {
  date: string;
  visitors: number;
  postsPublished: number;
}

export type AnalyticsRange = "7d" | "30d" | "90d";

export type DraftStatus = "draft" | "in-review";

export interface Draft {
  id: string;
  title: string;
  siteName: string;
  status: DraftStatus;
  updatedAt: string;
}

export type ServiceStatus = "operational" | "degraded" | "down";

export interface SystemHealth {
  apiStatus: ServiceStatus;
  wordpressConnection: SiteStatus;
  storageUsedPercent: number;
  backgroundQueue: {
    pending: number;
    status: ServiceStatus;
  };
}
