"use client";

import { useState } from "react";
import Link from "next/link";
import { AlertCircle, FileText } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { StatusBadge } from "@/components/common/status-badge";
import type { StatusBadgeStatus } from "@/components/common/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useSitePosts } from "@/features/wordpress/hooks/use-site-posts";
import type { ApiPost } from "@/services/api/posts.service";

const POST_STATUS_META: Record<
  ApiPost["status"],
  { label: string; badge: StatusBadgeStatus }
> = {
  draft: { label: "Draft", badge: "neutral" },
  "in-review": { label: "In Review", badge: "warning" },
  published: { label: "Published", badge: "success" },
};

interface PostsTableProps {
  siteId: number;
}

function PostsTable({ siteId }: PostsTableProps) {
  const [page, setPage] = useState(1);
  const { data, isPending, isError, refetch, isRefetching, isPlaceholderData } =
    useSitePosts(siteId, page);

  if (isPending) {
    return <LoadingState message="Loading posts…" />;
  }

  if (isError) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load posts"
        description="Something went wrong while loading this site's posts."
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

  const { posts, pagination } = data;

  if (posts.length === 0) {
    return (
      <EmptyState
        icon={FileText}
        title="No posts yet"
        description="Sync this site to pull in its WordPress posts."
      />
    );
  }

  return (
    <Card>
      <CardContent className="p-0">
        <ul className="divide-border flex flex-col divide-y">
          {posts.map((post) => {
            const meta = POST_STATUS_META[post.status];

            return (
              <li
                key={post.id}
                className="flex items-center justify-between gap-3 p-4"
              >
                <div className="flex min-w-0 items-center gap-3">
                  {post.featured_image ? (
                    // eslint-disable-next-line @next/next/no-img-element -- external/local storage URLs, not optimizable without configuring every possible disk host
                    <img
                      src={post.featured_image.url}
                      alt=""
                      className="bg-muted size-10 shrink-0 rounded-md object-cover"
                    />
                  ) : null}
                  <div className="flex min-w-0 flex-col gap-0.5">
                    <Link
                      href={`/wordpress/${siteId}/posts/${post.id}`}
                      className="hover:underline focus-visible:underline focus-visible:outline-none"
                    >
                      <Typography variant="body">{post.title}</Typography>
                    </Link>
                    {post.wordpress_modified_at ? (
                      <Typography variant="caption">
                        Modified{" "}
                        {new Date(
                          post.wordpress_modified_at,
                        ).toLocaleDateString()}
                      </Typography>
                    ) : null}
                  </div>
                </div>
                <StatusBadge status={meta.badge}>{meta.label}</StatusBadge>
              </li>
            );
          })}
        </ul>
      </CardContent>
      {pagination.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3 border-t p-4">
          <Typography variant="caption">
            Page {pagination.current_page} of {pagination.last_page} ·{" "}
            {pagination.total} posts
          </Typography>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage((current) => current - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={isPlaceholderData || page >= pagination.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </Card>
  );
}

export { PostsTable };
