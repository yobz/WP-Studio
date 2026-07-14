"use client";

import Link from "next/link";
import { AlertCircle, Globe } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Typography } from "@/components/ui/typography";
import { ConnectSiteDialog } from "@/features/wordpress/components/connect-site-dialog";
import { useSites } from "@/features/wordpress/hooks/use-sites";
import { SITE_STATUS_META } from "@/features/dashboard/utils/status-meta";

function SitesList() {
  const { data: sites, isPending, isError, refetch, isRefetching } = useSites();

  if (isPending) {
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} className="h-40 rounded-xl" />
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load your sites"
        description="Something went wrong while loading your connected WordPress sites."
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

  if (sites.length === 0) {
    return (
      <EmptyState
        icon={Globe}
        title="No sites connected"
        description="Connect your first WordPress site to start managing it from here."
        action={<ConnectSiteDialog />}
      />
    );
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {sites.map((site) => {
        const meta = SITE_STATUS_META[site.status];

        return (
          <Card
            key={site.id}
            data-slot="site-card"
            className="hover:ring-foreground/20"
          >
            <CardHeader>
              <div className="flex items-start justify-between gap-2">
                <CardTitle>
                  <Link
                    href={`/wordpress/${site.id}`}
                    className="hover:underline focus-visible:underline focus-visible:outline-none"
                  >
                    {site.name}
                  </Link>
                </CardTitle>
                <StatusBadge status={meta.badge}>{meta.label}</StatusBadge>
              </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              {site.url ? (
                <Typography
                  variant="body-sm"
                  className="text-muted-foreground truncate"
                >
                  {site.url}
                </Typography>
              ) : null}
              <div className="flex flex-wrap gap-x-4 gap-y-1">
                <Typography variant="caption">
                  {site.theme ?? "Unknown theme"}
                </Typography>
                {site.plugin_count !== null ? (
                  <Typography variant="caption">
                    {site.plugin_count} plugins
                  </Typography>
                ) : null}
              </div>
              {site.status === "error" && site.connection_error ? (
                <Typography variant="caption" className="text-destructive">
                  {site.connection_error}
                </Typography>
              ) : null}
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}

export { SitesList };
