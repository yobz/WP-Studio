"use client";

import { RefreshCw } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Typography } from "@/components/ui/typography";
import { useSyncSite } from "@/features/wordpress/hooks/use-sync-site";
import { ApiError } from "@/lib/api-client";

interface SyncButtonProps {
  siteId: number;
}

function SyncButton({ siteId }: SyncButtonProps) {
  const sync = useSyncSite(siteId);

  return (
    <div className="flex flex-col items-end gap-1">
      <Button
        variant="outline"
        size="sm"
        onClick={() => sync.mutate()}
        loading={sync.isPending}
      >
        <RefreshCw data-icon="inline-start" />
        Sync Content
      </Button>
      {sync.data ? (
        <Typography variant="caption">
          {sync.data.created} created · {sync.data.updated} updated ·{" "}
          {sync.data.skipped} unchanged
          {sync.data.failed > 0 ? ` · ${sync.data.failed} failed` : ""}
        </Typography>
      ) : sync.error ? (
        <Typography variant="caption" className="text-destructive">
          {sync.error instanceof ApiError ? sync.error.message : "Sync failed."}
        </Typography>
      ) : null}
    </div>
  );
}

export { SyncButton };
