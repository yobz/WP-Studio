import * as React from "react";
import { Loader2 } from "lucide-react";

import { Typography } from "@/components/ui/typography";
import { cn } from "@/lib/utils";

interface LoadingStateProps extends React.ComponentProps<"div"> {
  message?: string;
}

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
