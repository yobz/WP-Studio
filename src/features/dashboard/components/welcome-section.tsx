"use client";

import * as React from "react";
import { ChevronDown } from "lucide-react";

import { Skeleton } from "@/components/ui/skeleton";
import { Typography } from "@/components/ui/typography";

// A static, time-agnostic default — identical on the server-rendered
// HTML and the client's first paint, so the <h1> itself is never
// absent (see docs/ENGINEERING_JOURNAL.md, "Restoring a server-safe
// <h1>"). Only the *text inside* an already-present heading updates
// post-mount, which isn't a hydration mismatch — the DOM node exists
// identically in both renders.
const DEFAULT_GREETING = "Welcome back";

function getGreeting(hour: number): string {
  if (hour < 12) return "Good morning";
  if (hour < 18) return "Good afternoon";
  return "Good evening";
}

function WelcomeSection() {
  // The *date* still needs client-time evaluation to avoid a stale
  // build-time value (this route is statically generated) — deferring
  // it post-mount, same pattern as `ThemeToggle`
  // (src/components/common/theme-toggle.tsx). The heading text is
  // handled separately above: it always renders, it just starts
  // generic and becomes time-aware once mounted.
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

      {/*
        Placeholder — no real multi-workspace support yet (no auth).
        Native `disabled`, not `aria-disabled`: this is a plain button
        with no destination to guard against (unlike a disabled link,
        which can't take a native `disabled` attribute), so the
        simpler, correct primitive is the right one — see
        `QuickActions` for the same reasoning applied consistently.
      */}
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
