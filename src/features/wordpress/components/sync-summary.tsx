"use client";

import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useSyncStatus } from "@/features/wordpress/hooks/use-sync-status";

interface SyncSummaryProps {
  siteId: number;
}

function SyncSummary({ siteId }: SyncSummaryProps) {
  const { data: status, isPending, isError } = useSyncStatus(siteId);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Synchronization</CardTitle>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <LoadingState message="Loading sync status…" />
        ) : isError || !status ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load sync status"
            description="Something went wrong while loading synchronization status."
          />
        ) : (
          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
              <Typography variant="body">Posts synced</Typography>
              <Typography variant="body">{status.total_synced}</Typography>
            </div>
            <div className="flex items-center justify-between gap-2">
              <Typography variant="body">Last synced</Typography>
              <Typography variant="caption">
                {status.last_synced_at
                  ? new Date(status.last_synced_at).toLocaleString()
                  : "Never"}
              </Typography>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export { SyncSummary };
