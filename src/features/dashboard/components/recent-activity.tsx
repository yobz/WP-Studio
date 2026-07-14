"use client";

import * as React from "react";
import {
  AlertCircle,
  FileCheck2,
  FileEdit,
  History,
  PackageCheck,
  Sparkles,
  type LucideIcon,
} from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useRecentActivity } from "@/features/dashboard/hooks/use-recent-activity";
import type { ActivityType } from "@/features/dashboard/types/dashboard.types";
import { formatRelativeTime } from "@/lib/format";
import { useNotificationStore } from "@/store/notification-store";

const ACTIVITY_ICONS: Record<ActivityType, LucideIcon> = {
  "post-published": FileCheck2,
  "draft-created": FileEdit,
  "plugin-updated": PackageCheck,
  "ai-draft-generated": Sparkles,
};

function RecentActivity() {
  const {
    data: activity,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useRecentActivity();

  const setNotificationCount = useNotificationStore((state) => state.setCount);
  React.useEffect(() => {
    if (activity) setNotificationCount(activity.length);
  }, [activity, setNotificationCount]);

  return (
    <Card data-slot="recent-activity">
      <CardHeader>
        <CardTitle>Recent Activity</CardTitle>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <LoadingState message="Loading activity…" />
        ) : isError ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load activity"
            description="Something went wrong while loading recent activity."
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
        ) : activity.length === 0 ? (
          <EmptyState
            icon={History}
            title="No activity yet"
            description="Activity across your connected sites will show up here."
          />
        ) : (
          <ol className="flex flex-col gap-4">
            {activity.map((item) => {
              const Icon = ACTIVITY_ICONS[item.type];
              return (
                <li key={item.id} className="flex items-start gap-3">
                  <div className="bg-muted flex size-8 shrink-0 items-center justify-center rounded-full">
                    <Icon
                      className="text-muted-foreground size-4"
                      aria-hidden="true"
                    />
                  </div>
                  <div className="flex flex-col gap-0.5">
                    <Typography variant="body">{item.title}</Typography>
                    <Typography variant="caption">
                      {item.siteName} · {formatRelativeTime(item.timestamp)}
                    </Typography>
                  </div>
                </li>
              );
            })}
          </ol>
        )}
      </CardContent>
    </Card>
  );
}

export { RecentActivity };
