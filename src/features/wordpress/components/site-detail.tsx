"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { PageHeader } from "@/components/common/page-header";
import { StatusBadge } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { SITE_STATUS_META } from "@/features/dashboard/utils/status-meta";
import { useSite } from "@/features/wordpress/hooks/use-site";
import { sitePostsQueryKey } from "@/features/wordpress/hooks/use-site-posts";
import {
  useDeleteSite,
  useDisconnectSite,
  useRefreshMetadata,
  useVerifyConnection,
} from "@/features/wordpress/hooks/use-site-connection";
import { SyncButton } from "@/features/wordpress/components/sync-button";
import { SyncSummary } from "@/features/wordpress/components/sync-summary";
import { ApiError } from "@/lib/api-client";

interface SiteDetailProps {
  siteId: number;
}

function DetailField({
  label,
  value,
}: {
  label: string;
  value: React.ReactNode;
}) {
  return (
    <div>
      <Typography variant="caption">{label}</Typography>
      <Typography variant="body">{value ?? "Unknown"}</Typography>
    </div>
  );
}

function SiteDetail({ siteId }: SiteDetailProps) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const {
    data: site,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useSite(siteId);
  const verify = useVerifyConnection();
  const refresh = useRefreshMetadata();
  const disconnect = useDisconnectSite();
  const deleteSite = useDeleteSite();

  const wasSyncingRef = React.useRef(false);
  React.useEffect(() => {
    const isSyncing = site?.status === "syncing";
    if (wasSyncingRef.current && !isSyncing) {
      queryClient.invalidateQueries({ queryKey: sitePostsQueryKey(siteId) });
    }
    wasSyncingRef.current = isSyncing;
  }, [site?.status, siteId, queryClient]);

  if (isPending) {
    return <LoadingState message="Loading site…" className="min-h-[50vh]" />;
  }

  if (isError || !site) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load this site"
        description="It may have been removed, or something went wrong."
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

  const meta = SITE_STATUS_META[site.status];
  const actionError =
    verify.error ?? refresh.error ?? disconnect.error ?? deleteSite.error;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={site.name}
        description={site.url ?? undefined}
        actions={
          <div className="flex flex-wrap items-start gap-2">
            <Button
              variant="outline"
              size="sm"
              render={<Link href={`/wordpress/${site.id}/posts`} />}
              nativeButton={false}
            >
              View Posts
            </Button>
            <SyncButton siteId={site.id} syncing={site.status === "syncing"} />
            <Button
              variant="outline"
              size="sm"
              onClick={() => verify.mutate(site.id)}
              loading={verify.isPending}
            >
              Verify Connection
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => refresh.mutate(site.id)}
              loading={refresh.isPending}
            >
              Refresh Metadata
            </Button>
            {site.status !== "disconnected" ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => disconnect.mutate(site.id)}
                loading={disconnect.isPending}
              >
                Disconnect
              </Button>
            ) : null}
            <Button
              variant="destructive"
              size="sm"
              onClick={() =>
                deleteSite.mutate(site.id, {
                  onSuccess: () => router.push("/wordpress"),
                })
              }
              loading={deleteSite.isPending}
            >
              Remove
            </Button>
          </div>
        }
      />

      <div className="flex items-center gap-2">
        <StatusBadge status={meta.badge}>{meta.label}</StatusBadge>
        {site.last_checked_at ? (
          <Typography variant="caption">
            Last checked {new Date(site.last_checked_at).toLocaleString()}
          </Typography>
        ) : null}
      </div>

      {actionError ? (
        <Typography variant="body-sm" role="alert" className="text-destructive">
          {actionError instanceof ApiError
            ? actionError.message
            : "Something went wrong."}
        </Typography>
      ) : site.status === "error" && site.connection_error ? (
        <Typography variant="body-sm" role="alert" className="text-destructive">
          {site.connection_error}
        </Typography>
      ) : null}

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Site Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <DetailField
                label="WordPress Version"
                value={site.wordpress_version}
              />
              <DetailField label="PHP Version" value={site.php_version} />
              <DetailField label="Active Theme" value={site.theme} />
              <DetailField label="Plugins" value={site.plugin_count} />
              <DetailField label="Users" value={site.user_count} />
              <DetailField label="Timezone" value={site.timezone} />
              <DetailField label="Language" value={site.language} />
              <DetailField
                label="Plugin Updates"
                value={
                  site.plugin_updates_available === 0
                    ? "Up to date"
                    : `${site.plugin_updates_available} available`
                }
              />
            </div>
          </CardContent>
        </Card>
        <SyncSummary siteId={site.id} />
      </div>
    </div>
  );
}

export { SiteDetail };
