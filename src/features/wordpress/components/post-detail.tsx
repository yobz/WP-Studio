"use client";

import type * as React from "react";
import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { PageHeader } from "@/components/common/page-header";
import { StatusBadge } from "@/components/common/status-badge";
import type { StatusBadgeStatus } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { usePost } from "@/features/wordpress/hooks/use-post";
import type { ApiPost } from "@/services/api/posts.service";

const POST_STATUS_META: Record<
  ApiPost["status"],
  { label: string; badge: StatusBadgeStatus }
> = {
  draft: { label: "Draft", badge: "neutral" },
  "in-review": { label: "In Review", badge: "warning" },
  published: { label: "Published", badge: "success" },
};

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

interface PostDetailProps {
  postId: number;
}

function PostDetail({ postId }: PostDetailProps) {
  const {
    data: post,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = usePost(postId);

  if (isPending) {
    return <LoadingState message="Loading post…" className="min-h-[50vh]" />;
  }

  if (isError || !post) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load this post"
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

  const meta = POST_STATUS_META[post.status];

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={post.title}
        actions={
          post.wordpress_url ? (
            <Button
              variant="outline"
              size="sm"
              render={
                <a href={post.wordpress_url} target="_blank" rel="noreferrer" />
              }
              nativeButton={false}
            >
              View on WordPress
            </Button>
          ) : undefined
        }
      />

      <div className="flex items-center gap-2">
        <StatusBadge status={meta.badge}>{meta.label}</StatusBadge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Post Details</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <DetailField
              label="Published"
              value={
                post.published_at
                  ? new Date(post.published_at).toLocaleDateString()
                  : null
              }
            />
            <DetailField
              label="WordPress Modified"
              value={
                post.wordpress_modified_at
                  ? new Date(post.wordpress_modified_at).toLocaleDateString()
                  : null
              }
            />
            <DetailField
              label="Last Synced"
              value={
                post.last_synced_at
                  ? new Date(post.last_synced_at).toLocaleString()
                  : null
              }
            />
            <DetailField label="Sync Status" value={post.sync_status} />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export { PostDetail };
