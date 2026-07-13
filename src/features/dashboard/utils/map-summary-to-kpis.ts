import type { DashboardSummary } from "@/services/api/dashboard.service";
import type { Kpi } from "@/features/dashboard/types/dashboard.types";

/**
 * Adapts the real API's raw response into the exact `Kpi[]` shape
 * `KpiCards` already renders — the whole point of this milestone's
 * frontend change (see docs/adr/0004-backend-foundation.md,
 * "Migration strategy"): the widget component needs zero changes,
 * only the data-shaping between the API and the existing contract.
 *
 * `trend` is only populated for Monthly Visitors — as of Milestone 7,
 * that's the one KPI with a real historical baseline behind it
 * (AnalyticsSnapshot; see docs/adr/0005-domain-model.md). The other
 * four KPIs are still point-in-time counts with nothing to compare
 * against yet, so `trend` stays omitted for them — honest about what
 * the API can currently support, not an oversight.
 */
export function mapSummaryToKpis(summary: DashboardSummary): Kpi[] {
  const storageUsedGb = (summary.storage_used_mb / 1024).toFixed(1);
  const storageLimitGb = Math.round(summary.storage_limit_mb / 1024);

  return [
    {
      id: "connected-sites",
      label: "Connected Sites",
      value: String(summary.connected_sites),
    },
    {
      id: "published-posts",
      label: "Published Posts",
      value: summary.published_posts.toLocaleString(),
    },
    {
      id: "draft-posts",
      label: "Draft Posts",
      value: String(summary.draft_posts),
    },
    {
      id: "monthly-visitors",
      label: "Monthly Visitors",
      value: summary.monthly_visitors.toLocaleString(),
      trend: formatVisitorsTrend(summary.monthly_visitors_trend),
    },
    {
      id: "storage-usage",
      label: "Storage Usage",
      value: `${storageUsedGb} GB / ${storageLimitGb} GB`,
    },
  ];
}

function formatVisitorsTrend(percentChange: number | null): Kpi["trend"] {
  if (percentChange === null) return undefined;

  const direction =
    percentChange > 0 ? "up" : percentChange < 0 ? "down" : "neutral";
  const sign = percentChange > 0 ? "+" : "";

  return {
    value: `${sign}${percentChange}% vs. prior 14 days`,
    direction,
  };
}
