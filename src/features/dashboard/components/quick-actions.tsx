import Link from "next/link";
import {
  BarChart3,
  Link2,
  PenSquare,
  Sparkles,
  type LucideIcon,
} from "lucide-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import type { QuickAction } from "@/features/dashboard/types/dashboard.types";

interface QuickActionConfig extends QuickAction {
  icon: LucideIcon;
  href?: string;
}

const QUICK_ACTIONS: QuickActionConfig[] = [
  {
    id: "new-post",
    title: "New Post",
    description: "Start writing a new post for one of your sites.",
    icon: PenSquare,
  },
  {
    id: "generate-ai-draft",
    title: "Generate AI Draft",
    description: "Let AI draft a starting point from a prompt.",
    icon: Sparkles,
  },
  {
    id: "connect-site",
    title: "Connect WordPress Site",
    description: "Link another WordPress install to your workspace.",
    icon: Link2,
    href: "/wordpress",
  },
  {
    id: "view-analytics",
    title: "View Analytics",
    description: "See traffic and engagement across your sites.",
    icon: BarChart3,
    href: "/analytics",
  },
];

function QuickActions() {
  return (
    <Card data-slot="quick-actions">
      <CardHeader>
        <CardTitle>Quick Actions</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {QUICK_ACTIONS.map((action) => {
          const Icon = action.icon;
          const content = (
            <>
              <Icon className="text-primary size-5" aria-hidden="true" />
              <Typography variant="label">{action.title}</Typography>
              <Typography variant="caption">{action.description}</Typography>
            </>
          );

          if (action.href) {
            return (
              <Link
                key={action.id}
                href={action.href}
                className="border-border bg-background hover:bg-muted focus-visible:outline-ring flex flex-col items-start gap-2 rounded-lg border p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
              >
                {content}
              </Link>
            );
          }

          return (
            <button
              key={action.id}
              type="button"
              disabled
              className="border-border bg-background flex flex-col items-start gap-2 rounded-lg border p-3 text-left disabled:pointer-events-none disabled:opacity-60"
            >
              {content}
            </button>
          );
        })}
      </CardContent>
    </Card>
  );
}

export { QuickActions };
