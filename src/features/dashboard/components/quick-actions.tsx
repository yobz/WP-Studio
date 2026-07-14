import {
  BarChart3,
  Link2,
  PenSquare,
  Sparkles,
  type LucideIcon,
} from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { mockQuickActions } from "@/services/mock/dashboard.mock-data";
import type { QuickAction } from "@/features/dashboard/types/dashboard.types";

const QUICK_ACTION_ICONS: Record<QuickAction["id"], LucideIcon> = {
  "new-post": PenSquare,
  "generate-ai-draft": Sparkles,
  "connect-site": Link2,
  "view-analytics": BarChart3,
};

function QuickActions() {
  return (
    <Card data-slot="quick-actions">
      <CardHeader>
        <CardTitle>Quick Actions</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {mockQuickActions.map((action) => {
          const Icon = QUICK_ACTION_ICONS[action.id];
          return (
            <button
              key={action.id}
              type="button"
              disabled
              className="border-border bg-background flex flex-col items-start gap-2 rounded-lg border p-3 text-left disabled:pointer-events-none disabled:opacity-60"
            >
              <Icon className="text-primary size-5" aria-hidden="true" />
              <Typography variant="label">{action.title}</Typography>
              <Typography variant="caption">{action.description}</Typography>
            </button>
          );
        })}
      </CardContent>
    </Card>
  );
}

export { QuickActions };
