import { BarChart3 } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { PageHeader } from "@/components/common/page-header";

export default function AnalyticsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Analytics"
        description="Traffic and engagement insights across your sites."
      />
      <EmptyState
        icon={BarChart3}
        title="No analytics yet"
        description="Charts and reports will appear here once analytics data is connected."
      />
    </div>
  );
}
