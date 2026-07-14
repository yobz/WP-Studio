"use client";

import { Card, CardContent } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { formatFileSize } from "@/features/media/utils/format-file-size";
import type { ApiMedia } from "@/services/api/media.service";

interface MediaListProps {
  items: ApiMedia[];
  onSelect: (item: ApiMedia) => void;
}

function MediaList({ items, onSelect }: MediaListProps) {
  return (
    <Card>
      <CardContent className="p-0">
        <ul className="divide-border flex flex-col divide-y">
          {items.map((item) => (
            <li key={item.id}>
              <button
                type="button"
                onClick={() => onSelect(item)}
                className="hover:bg-muted focus-visible:bg-muted flex w-full items-center gap-3 p-3 text-left outline-none"
              >
                <span className="bg-muted size-12 shrink-0 overflow-hidden rounded-md">
                  {/* eslint-disable-next-line @next/next/no-img-element -- external/local storage URLs, not optimizable without configuring every possible disk host */}
                  <img
                    src={item.url}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                  />
                </span>
                <span className="flex min-w-0 flex-col gap-0.5">
                  <Typography variant="body" className="truncate">
                    {item.filename}
                  </Typography>
                  <Typography variant="caption">
                    {item.width && item.height
                      ? `${item.width}×${item.height} · `
                      : ""}
                    {formatFileSize(item.size)} · {item.source}
                  </Typography>
                </span>
              </button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

export { MediaList };
