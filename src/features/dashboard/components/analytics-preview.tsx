"use client";

import * as React from "react";
import { AlertCircle, LineChart as LineChartIcon } from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
} from "recharts";

import { EmptyState } from "@/components/common/empty-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAnalyticsPreview } from "@/features/dashboard/hooks/use-analytics-preview";
import type { AnalyticsRange } from "@/features/dashboard/types/dashboard.types";
import { cn } from "@/lib/utils";

const RANGE_OPTIONS: { value: AnalyticsRange; label: string }[] = [
  { value: "7d", label: "7D" },
  { value: "30d", label: "30D" },
  { value: "90d", label: "90D" },
];

function formatTick(date: string) {
  return new Date(date).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
}

function AnalyticsPreview() {
  const [range, setRange] = React.useState<AnalyticsRange>("7d");
  const {
    data: points,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useAnalyticsPreview(range);

  return (
    <Card data-slot="analytics-preview">
      <CardHeader className="flex-row items-center justify-between">
        <CardTitle>Analytics Preview</CardTitle>
        <div
          role="group"
          aria-label="Select time range"
          className="flex items-center gap-1"
        >
          {RANGE_OPTIONS.map((option) => (
            <Button
              key={option.value}
              type="button"
              size="sm"
              variant={range === option.value ? "secondary" : "ghost"}
              aria-pressed={range === option.value}
              onClick={() => setRange(option.value)}
            >
              {option.label}
            </Button>
          ))}
        </div>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : isError ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load analytics"
            description="Something went wrong while loading traffic data."
            action={
              <Button
                variant="outline"
                size="sm"
                onClick={() => refetch()}
                loading={isRefetching}
              >
                Try again
              </Button>
            }
          />
        ) : points.length === 0 ? (
          <EmptyState
            icon={LineChartIcon}
            title="No traffic data yet"
            description="Visitor trends will appear here once a site is connected."
          />
        ) : (
          <div
            role="img"
            aria-label={`Visitor trend over the last ${range === "7d" ? "7 days" : range === "30d" ? "30 days" : "90 days"}, ranging from ${Math.min(...points.map((p) => p.visitors))} to ${Math.max(...points.map((p) => p.visitors))} visitors.`}
            className={cn("h-48 w-full")}
          >
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart
                data={points}
                margin={{ top: 8, right: 8, left: 8, bottom: 0 }}
              >
                <defs>
                  <linearGradient
                    id="visitors-fill"
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="1"
                  >
                    <stop
                      offset="0%"
                      stopColor="var(--chart-1)"
                      stopOpacity={0.35}
                    />
                    <stop
                      offset="100%"
                      stopColor="var(--chart-1)"
                      stopOpacity={0}
                    />
                  </linearGradient>
                </defs>
                <CartesianGrid vertical={false} stroke="var(--border)" />
                <XAxis
                  dataKey="date"
                  tickFormatter={formatTick}
                  tickLine={false}
                  axisLine={false}
                  fontSize={12}
                  stroke="var(--muted-foreground)"
                  minTickGap={24}
                />
                <Tooltip
                  labelFormatter={(label) => formatTick(String(label))}
                  contentStyle={{
                    background: "var(--popover)",
                    border: "1px solid var(--border)",
                    borderRadius: "var(--radius-md)",
                    fontSize: 12,
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="visitors"
                  stroke="var(--chart-1)"
                  strokeWidth={2}
                  fill="url(#visitors-fill)"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export { AnalyticsPreview };
