"use client";

import type { ApiMedia } from "@/services/api/media.service";

interface MediaGridProps {
  items: ApiMedia[];
  onSelect: (item: ApiMedia) => void;
}

function MediaGrid({ items, onSelect }: MediaGridProps) {
  return (
    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      {items.map((item) => (
        <li key={item.id}>
          <button
            type="button"
            onClick={() => onSelect(item)}
            className="border-border bg-muted focus-visible:border-ring focus-visible:ring-ring/50 group aspect-square w-full overflow-hidden rounded-lg border outline-none focus-visible:ring-3"
          >
            {/* eslint-disable-next-line @next/next/no-img-element -- external/local storage URLs, not optimizable by next/image without configuring every possible disk host */}
            <img
              src={item.url}
              alt={item.alt_text || item.filename}
              loading="lazy"
              className="size-full object-cover transition-transform group-hover:scale-105"
            />
          </button>
        </li>
      ))}
    </ul>
  );
}

export { MediaGrid };
