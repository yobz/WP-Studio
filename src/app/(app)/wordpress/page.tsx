import { Globe } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { PageHeader } from "@/components/common/page-header";

export default function WordPressPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="WordPress"
        description="Connect and manage your WordPress site integrations."
      />
      <EmptyState
        icon={Globe}
        title="No sites connected"
        description="Connect your first WordPress site to start managing it from here."
      />
    </div>
  );
}
