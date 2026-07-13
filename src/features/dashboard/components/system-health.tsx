"use client";

import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Progress,
  ProgressLabel,
  ProgressValue,
} from "@/components/ui/progress";
import { Typography } from "@/components/ui/typography";
import { useSystemHealth } from "@/features/dashboard/hooks/use-system-health";
import {
  SERVICE_STATUS_META,
  SITE_STATUS_META,
} from "@/features/dashboard/utils/status-meta";

function SystemHealth() {
  const {
    data: health,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useSystemHealth();

  return (
    <Card data-slot="system-health">
      <CardHeader>
        <CardTitle>System Health</CardTitle>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <LoadingState message="Loading system health…" />
        ) : isError ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load system health"
            description="Something went wrong while loading system status."
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
        ) : (
          <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-2">
              <Typography variant="body">API Status</Typography>
              <StatusBadge status={SERVICE_STATUS_META[health.apiStatus].badge}>
                {SERVICE_STATUS_META[health.apiStatus].label}
              </StatusBadge>
            </div>
            <div className="flex items-center justify-between gap-2">
              <Typography variant="body">WordPress Connection</Typography>
              <StatusBadge
                status={SITE_STATUS_META[health.wordpressConnection].badge}
              >
                {SITE_STATUS_META[health.wordpressConnection].label}
              </StatusBadge>
            </div>
            <div className="flex items-center justify-between gap-2">
              <Typography variant="body">Background Queue</Typography>
              <div className="flex items-center gap-2">
                <Typography variant="caption">
                  {health.backgroundQueue.pending} pending
                </Typography>
                <StatusBadge
                  status={
                    SERVICE_STATUS_META[health.backgroundQueue.status].badge
                  }
                >
                  {SERVICE_STATUS_META[health.backgroundQueue.status].label}
                </StatusBadge>
              </div>
            </div>
            <Progress value={health.storageUsedPercent}>
              <div className="flex w-full items-center justify-between">
                <ProgressLabel>Storage Used</ProgressLabel>
                <ProgressValue />
              </div>
            </Progress>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export { SystemHealth };
