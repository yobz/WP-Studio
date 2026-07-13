import * as React from "react";
import { type LucideIcon, Minus, TrendingDown, TrendingUp } from "lucide-react";

import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { cn } from "@/lib/utils";

interface StatCardTrend {
  value: string;
  direction: "up" | "down" | "neutral";
}

interface StatCardProps extends React.ComponentProps<"div"> {
  label: string;
  value: string | number;
  icon?: LucideIcon;
  trend?: StatCardTrend;
}

const trendConfig: Record<
  StatCardTrend["direction"],
  { icon: LucideIcon; className: string }
> = {
  up: { icon: TrendingUp, className: "text-success" },
  down: { icon: TrendingDown, className: "text-destructive" },
  neutral: { icon: Minus, className: "text-muted-foreground" },
};

function StatCard({
  label,
  value,
  icon: Icon,
  trend,
  className,
  ...props
}: StatCardProps) {
  const trendMeta = trend ? trendConfig[trend.direction] : null;

  return (
    <Card data-slot="stat-card" className={cn("gap-2", className)} {...props}>
      <CardHeader className="flex items-center justify-between gap-2">
        <Typography variant="caption">{label}</Typography>
        {Icon ? <Icon className="text-muted-foreground size-4" /> : null}
      </CardHeader>
      <CardContent className="flex items-end justify-between gap-2">
        <Typography as="span" variant="h2">
          {value}
        </Typography>
        {trendMeta && trend ? (
          <span
            className={cn(
              "flex items-center gap-1 text-xs font-medium",
              trendMeta.className,
            )}
          >
            <trendMeta.icon className="size-3.5" />
            {trend.value}
          </span>
        ) : null}
      </CardContent>
    </Card>
  );
}

export { StatCard };
