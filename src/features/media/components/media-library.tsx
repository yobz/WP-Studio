"use client";

import * as React from "react";
import {
  AlertCircle,
  Image as ImageIcon,
  LayoutGrid,
  List,
} from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { PageHeader } from "@/components/common/page-header";
import { Button } from "@/components/ui/button";
import { MediaGrid } from "@/features/media/components/media-grid";
import { MediaList } from "@/features/media/components/media-list";
import { MediaPreviewDialog } from "@/features/media/components/media-preview-dialog";
import { MediaUploadButton } from "@/features/media/components/media-upload-button";
import { useMedia } from "@/features/media/hooks/use-media";
import type { ApiMedia } from "@/services/api/media.service";

type ViewMode = "grid" | "list";

function MediaLibrary() {
  const [viewMode, setViewMode] = React.useState<ViewMode>("grid");
  const [selected, setSelected] = React.useState<ApiMedia | null>(null);
  const { data: items, isPending, isError, refetch, isRefetching } = useMedia();

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Media Library"
        description="Every file WP Studio has uploaded or synced from WordPress, in one place."
        actions={<MediaUploadButton />}
      />

      {isPending ? (
        <LoadingState message="Loading media…" />
      ) : isError ? (
        <EmptyState
          icon={AlertCircle}
          title="Couldn't load media"
          description="Something went wrong while loading your media library."
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
      ) : items.length === 0 ? (
        <EmptyState
          icon={ImageIcon}
          title="No media yet"
          description="Upload a file, or sync a WordPress site with featured images."
        />
      ) : (
        <>
          <div
            className="flex justify-end gap-1"
            role="group"
            aria-label="View"
          >
            <Button
              variant={viewMode === "grid" ? "secondary" : "ghost"}
              size="icon-sm"
              aria-label="Grid view"
              aria-pressed={viewMode === "grid"}
              onClick={() => setViewMode("grid")}
            >
              <LayoutGrid />
            </Button>
            <Button
              variant={viewMode === "list" ? "secondary" : "ghost"}
              size="icon-sm"
              aria-label="List view"
              aria-pressed={viewMode === "list"}
              onClick={() => setViewMode("list")}
            >
              <List />
            </Button>
          </div>

          {viewMode === "grid" ? (
            <MediaGrid items={items} onSelect={setSelected} />
          ) : (
            <MediaList items={items} onSelect={setSelected} />
          )}
        </>
      )}

      <MediaPreviewDialog
        item={selected}
        onOpenChange={(open) => {
          if (!open) setSelected(null);
        }}
      />
    </div>
  );
}

export { MediaLibrary };
