"use client";

import {
  AlertCircle,
  FileEdit,
  FileText,
  Globe,
  HardDrive,
  Users,
  type LucideIcon,
} from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { StatCard } from "@/components/common/stat-card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useKpis } from "@/features/dashboard/hooks/use-kpis";
import type { Kpi } from "@/features/dashboard/types/dashboard.types";

const KPI_ICONS: Record<Kpi["id"], LucideIcon> = {
  "connected-sites": Globe,
  "published-posts": FileText,
  "draft-posts": FileEdit,
  "monthly-visitors": Users,
  "storage-usage": HardDrive,
};

const SKELETON_COUNT = 5;

function KpiCards() {
  const { data: kpis, isPending, isError, refetch, isRefetching } = useKpis();

  if (isPending) {
    return (
      <div
        role="status"
        aria-label="Loading key metrics"
        className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
      >
        {Array.from({ length: SKELETON_COUNT }).map((_, index) => (
          <Skeleton key={index} className="h-24 w-full" />
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load your metrics"
        description="Something went wrong while loading key metrics."
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
    );
  }

  if (kpis.length === 0) {
    return (
      <EmptyState
        title="No metrics yet"
        description="Connect a WordPress site to start seeing metrics here."
      />
    );
  }

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
      {kpis.map((kpi) => (
        <StatCard
          key={kpi.id}
          label={kpi.label}
          value={kpi.value}
          trend={kpi.trend}
          icon={KPI_ICONS[kpi.id]}
        />
      ))}
    </div>
  );
}

export { KpiCards };
