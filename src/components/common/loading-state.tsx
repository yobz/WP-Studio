import * as React from "react";
import { Loader2 } from "lucide-react";

import { Typography } from "@/components/ui/typography";
import { cn } from "@/lib/utils";

interface LoadingStateProps extends React.ComponentProps<"div"> {
  message?: string;
}

/**
 * Generic in-content loading placeholder — a centered spinner with an
 * optional message. For loading UI that needs to match a specific final
 * shape (a KPI grid, a chart), compose `Skeleton` directly instead; this
 * is for widgets where a shape-matched skeleton isn't worth the extra
 * markup (e.g. a short list or a single async section).
 */
function LoadingState({ message, className, ...props }: LoadingStateProps) {
  return (
    <div
      data-slot="loading-state"
      role="status"
      className={cn(
        "flex flex-col items-center justify-center gap-2 px-6 py-12 text-center",
        className,
      )}
      {...props}
    >
      <Loader2 className="text-muted-foreground size-5 animate-spin" />
      {message ? (
        <Typography variant="body-sm" className="text-muted-foreground">
          {message}
        </Typography>
      ) : null}
      <span className="sr-only">Loading{message ? `: ${message}` : ""}</span>
    </div>
  );
}

export { LoadingState };
