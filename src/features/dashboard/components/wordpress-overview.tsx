"use client";

import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useWordPressOverview } from "@/features/dashboard/hooks/use-wordpress-overview";
import { SITE_STATUS_META } from "@/features/dashboard/utils/status-meta";

function WordPressOverview() {
  const {
    data: overview,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useWordPressOverview();

  return (
    <Card data-slot="wordpress-overview">
      <CardHeader>
        <CardTitle>WordPress Overview</CardTitle>
      </CardHeader>
      <CardContent>
        {isPending ? (
          <LoadingState message="Loading site overview…" />
        ) : isError ? (
          <EmptyState
            icon={AlertCircle}
            title="Couldn't load site overview"
            description="Something went wrong while loading your WordPress site."
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
              <Typography variant="label">{overview.siteName}</Typography>
              <StatusBadge status={SITE_STATUS_META[overview.status].badge}>
                {SITE_STATUS_META[overview.status].label}
              </StatusBadge>
            </div>
            <dl className="grid grid-cols-2 gap-3">
              <div>
                <dt>
                  <Typography variant="caption">WordPress Version</Typography>
                </dt>
                <dd>
                  <Typography variant="body">
                    {overview.wordpressVersion}
                  </Typography>
                </dd>
              </div>
              <div>
                <dt>
                  <Typography variant="caption">Active Theme</Typography>
                </dt>
                <dd>
                  <Typography variant="body">{overview.theme}</Typography>
                </dd>
              </div>
              <div>
                <dt>
                  <Typography variant="caption">Plugin Updates</Typography>
                </dt>
                <dd>
                  <Typography variant="body">
                    {overview.pluginUpdatesAvailable === 0
                      ? "Up to date"
                      : `${overview.pluginUpdatesAvailable} available`}
                  </Typography>
                </dd>
              </div>
            </dl>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export { WordPressOverview };
