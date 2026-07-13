"use client";

import * as React from "react";
import { Search, X } from "lucide-react";

import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

interface SearchInputProps extends Omit<
  React.ComponentProps<typeof Input>,
  "type"
> {
  onClear?: () => void;
}

function SearchInput({
  className,
  value,
  onClear,
  ...props
}: SearchInputProps) {
  return (
    <div data-slot="search-input" className="relative">
      <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
      <Input
        type="search"
        value={value}
        className={cn(
          "pl-8 [&::-webkit-search-cancel-button]:appearance-none",
          value ? "pr-8" : undefined,
          className,
        )}
        {...props}
      />
      {value ? (
        <button
          type="button"
          onClick={onClear}
          aria-label="Clear search"
          className="text-muted-foreground hover:text-foreground focus-visible:outline-ring absolute top-1/2 right-2 -translate-y-1/2 rounded-sm transition-colors focus-visible:outline-2"
        >
          <X className="size-4" />
        </button>
      ) : null}
    </div>
  );
}

export { SearchInput };
