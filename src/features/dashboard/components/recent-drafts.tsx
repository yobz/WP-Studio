"use client";

import { AlertCircle, FileText } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { StatusBadge } from "@/components/common/status-badge";
import type { StatusBadgeStatus } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useRecentDrafts } from "@/features/dashboard/hooks/use-recent-drafts";
import type { DraftStatus } from "@/features/dashboard/types/dashboard.types";
import { formatRelativeTime } from "@/lib/format";

const DRAFT_STATUS_META: Record<
  DraftStatus,
  { label: string; badge: StatusBadgeStatus }
> = {
  draft: { label: "Draft", badge: "neutral" },
  "in-review": { label: "In Review", badge: "warning" },
};

function RecentDrafts() {
  const {
    data: drafts,
    isPending,
    isError,
    error,
    refetch,
    isRefetching,
  } = useRecentDrafts();

  return (
    <Card data-slot="recent-drafts">
      <CardHeader>
        <CardTitle>Recent Drafts</CardTitle>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <LoadingState message="Loading drafts…" />
        ) : isError ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load drafts"
            description={
              error instanceof Error
                ? error.message
                : "Something went wrong while loading recent drafts."
            }
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
        ) : drafts.length === 0 ? (
          <EmptyState
            icon={FileText}
            title="No drafts yet"
            description="Posts you start writing will show up here before they're published."
          />
        ) : (
          <ul className="divide-border flex flex-col divide-y">
            {drafts.map((draft) => (
              <li
                key={draft.id}
                className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
              >
                <div className="flex flex-col gap-0.5">
                  <Typography variant="body">{draft.title}</Typography>
                  <Typography variant="caption">
                    {draft.siteName} · Updated{" "}
                    {formatRelativeTime(draft.updatedAt)}
                  </Typography>
                </div>
                <StatusBadge status={DRAFT_STATUS_META[draft.status].badge}>
                  {DRAFT_STATUS_META[draft.status].label}
                </StatusBadge>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

export { RecentDrafts };
