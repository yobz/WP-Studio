"use client";

import * as React from "react";
import { ChevronDown } from "lucide-react";

import { Skeleton } from "@/components/ui/skeleton";
import { Typography } from "@/components/ui/typography";

const DEFAULT_GREETING = "Welcome back";

function getGreeting(hour: number): string {
  if (hour < 12) return "Good morning";
  if (hour < 18) return "Good afternoon";
  return "Good evening";
}

function WelcomeSection() {
  const [mounted, setMounted] = React.useState(false);
  React.useEffect(() => setMounted(true), []);

  const now = new Date();

  return (
    <section className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-col gap-1">
        <Typography as="h1" variant="h1">
          {mounted ? getGreeting(now.getHours()) : DEFAULT_GREETING}
        </Typography>
        <Typography variant="body" className="text-muted-foreground">
          Manage content, WordPress sites, and analytics from one place.
        </Typography>
        {mounted ? (
          <Typography variant="caption">
            {new Intl.DateTimeFormat("en-US", {
              weekday: "long",
              month: "long",
              day: "numeric",
              year: "numeric",
            }).format(now)}
          </Typography>
        ) : (
          <Skeleton className="h-4 w-40" />
        )}
      </div>

      <button
        type="button"
        disabled
        className="border-border bg-card text-card-foreground flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium disabled:pointer-events-none disabled:opacity-50"
      >
        My Workspace
        <ChevronDown className="text-muted-foreground size-4" />
      </button>
    </section>
  );
}

export { WelcomeSection };
