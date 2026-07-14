"use client";

import { RefreshCw } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Typography } from "@/components/ui/typography";
import { useSyncSite } from "@/features/wordpress/hooks/use-sync-site";
import { ApiError } from "@/lib/api-client";

interface SyncButtonProps {
  siteId: number;
  syncing: boolean;
}

function SyncButton({ siteId, syncing }: SyncButtonProps) {
  const sync = useSyncSite(siteId);

  return (
    <div className="flex flex-col items-end gap-1">
      <Button
        variant="outline"
        size="sm"
        onClick={() => sync.mutate()}
        loading={sync.isPending}
        disabled={syncing}
      >
        <RefreshCw data-icon="inline-start" />
        Sync Content
      </Button>
      {syncing ? (
        <Typography variant="caption" role="status">
          Syncing — this page updates automatically…
        </Typography>
      ) : sync.isSuccess ? (
        <Typography variant="caption" role="status">
          Sync queued.
        </Typography>
      ) : sync.error ? (
        <Typography variant="caption" role="alert" className="text-destructive">
          {sync.error instanceof ApiError ? sync.error.message : "Sync failed."}
        </Typography>
      ) : null}
    </div>
  );
}

export { SyncButton };
