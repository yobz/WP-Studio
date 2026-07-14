import type { DashboardSummary } from "@/services/api/dashboard.service";
import type { Kpi } from "@/features/dashboard/types/dashboard.types";

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
