import { describe, expect, it } from "vitest";

import { mapSummaryToKpis } from "./map-summary-to-kpis";
import type { GraphQLDashboardSummary } from "@/features/dashboard/hooks/use-dashboard-overview";

const baseSummary: GraphQLDashboardSummary = {
  connectedSites: 3,
  publishedPosts: 42,
  draftPosts: 5,
  storageUsedMb: 2048,
  storageLimitMb: 10240,
  monthlyVisitors: 12345,
  monthlyVisitorsTrend: null,
};

describe("mapSummaryToKpis", () => {
  it("maps every summary field to its corresponding KPI", () => {
    const kpis = mapSummaryToKpis(baseSummary);

    expect(kpis).toHaveLength(5);
    expect(kpis.find((k) => k.id === "connected-sites")?.value).toBe("3");
    expect(kpis.find((k) => k.id === "published-posts")?.value).toBe("42");
    expect(kpis.find((k) => k.id === "draft-posts")?.value).toBe("5");
    expect(kpis.find((k) => k.id === "monthly-visitors")?.value).toBe("12,345");
  });

  it("formats storage usage as GB, rounding the limit", () => {
    const kpis = mapSummaryToKpis(baseSummary);

    expect(kpis.find((k) => k.id === "storage-usage")?.value).toBe(
      "2.0 GB / 10 GB",
    );
  });

  it("omits the trend when there is no prior-period data", () => {
    const kpis = mapSummaryToKpis(baseSummary);

    expect(
      kpis.find((k) => k.id === "monthly-visitors")?.trend,
    ).toBeUndefined();
  });

  it("renders a positive trend with an explicit sign and 'up' direction", () => {
    const kpis = mapSummaryToKpis({
      ...baseSummary,
      monthlyVisitorsTrend: 12.5,
    });

    expect(kpis.find((k) => k.id === "monthly-visitors")?.trend).toEqual({
      value: "+12.5% vs. prior 14 days",
      direction: "up",
    });
  });

  it("renders a negative trend without a sign and 'down' direction", () => {
    const kpis = mapSummaryToKpis({
      ...baseSummary,
      monthlyVisitorsTrend: -8,
    });

    expect(kpis.find((k) => k.id === "monthly-visitors")?.trend).toEqual({
      value: "-8% vs. prior 14 days",
      direction: "down",
    });
  });

  it("renders a zero trend as 'neutral'", () => {
    const kpis = mapSummaryToKpis({ ...baseSummary, monthlyVisitorsTrend: 0 });

    expect(kpis.find((k) => k.id === "monthly-visitors")?.trend).toEqual({
      value: "0% vs. prior 14 days",
      direction: "neutral",
    });
  });
});
