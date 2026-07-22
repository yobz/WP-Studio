"use client";

import dynamic from "next/dynamic";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

/**
 * recharts is the single largest dependency on the dashboard route (it
 * roughly doubles the page's First Load JS) — split into its own chunk and
 * loaded client-side only, so the rest of the dashboard doesn't wait on a
 * charting library to hydrate. The loading state mirrors the real card's
 * shape (header + h-48 chart area) so nothing visibly shifts on swap-in.
 */
const AnalyticsPreview = dynamic(
  () =>
    import("@/features/dashboard/components/analytics-preview").then(
      (mod) => mod.AnalyticsPreview,
    ),
  {
    ssr: false,
    loading: () => (
      <Card data-slot="analytics-preview">
        <CardHeader>
          <CardTitle>Analytics Preview</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-48 w-full" />
        </CardContent>
      </Card>
    ),
  },
);

export { AnalyticsPreview };
