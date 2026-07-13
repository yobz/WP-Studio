import * as React from "react";

import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type StatusBadgeStatus = "success" | "warning" | "error" | "neutral";

interface StatusBadgeProps extends Omit<
  React.ComponentProps<typeof Badge>,
  "variant"
> {
  status: StatusBadgeStatus;
}

const statusStyles: Record<StatusBadgeStatus, string> = {
  success: "bg-success/10 text-success dark:bg-success/20",
  warning: "bg-warning/10 text-warning dark:bg-warning/20",
  error: "bg-destructive/10 text-destructive dark:bg-destructive/20",
  neutral: "bg-muted text-muted-foreground",
};

function StatusBadge({ status, className, ...props }: StatusBadgeProps) {
  return (
    <Badge
      data-slot="status-badge"
      variant="outline"
      className={cn("border-transparent", statusStyles[status], className)}
      {...props}
    />
  );
}

export { StatusBadge };
export type { StatusBadgeStatus };
