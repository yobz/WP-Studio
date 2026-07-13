"use client";

import * as React from "react";
import { AlertTriangle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { Button } from "@/components/ui/button";

interface AppGroupErrorProps {
  error: Error & { digest?: string };
  reset: () => void;
}

export default function AppGroupError({ error, reset }: AppGroupErrorProps) {
  React.useEffect(() => {
    // Placeholder for future error reporting (Sentry, etc.) — logged for
    // now so failures aren't silently swallowed during development.
    console.error(error);
  }, [error]);

  return (
    <div className="flex flex-col gap-6">
      <EmptyState
        icon={AlertTriangle}
        title="Something went wrong"
        titleAs="h1"
        description="An unexpected error occurred while loading this page."
        action={<Button onClick={() => reset()}>Try again</Button>}
      />
    </div>
  );
}
