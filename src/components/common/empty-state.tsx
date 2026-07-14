import * as React from "react";
import { type LucideIcon } from "lucide-react";

import { Typography } from "@/components/ui/typography";
import { cn } from "@/lib/utils";

interface EmptyStateProps extends React.ComponentProps<"div"> {
  icon?: LucideIcon;
  title: string;
  titleAs?: "h1" | "h2" | "h3";
  description?: string;
  action?: React.ReactNode;
}

function EmptyState({
  icon: Icon,
  title,
  titleAs = "h2",
  description,
  action,
  className,
  ...props
}: EmptyStateProps) {
  return (
    <div
      data-slot="empty-state"
      className={cn(
        "border-border flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center",
        className,
      )}
      {...props}
    >
      {Icon ? (
        <div className="bg-muted flex size-10 items-center justify-center rounded-full">
          <Icon className="text-muted-foreground size-5" />
        </div>
      ) : null}
      <div className="flex flex-col gap-1">
        <Typography as={titleAs} variant="h4">
          {title}
        </Typography>
        {description ? (
          <Typography variant="body-sm" className="text-muted-foreground">
            {description}
          </Typography>
        ) : null}
      </div>
      {action}
    </div>
  );
}

export { EmptyState };
