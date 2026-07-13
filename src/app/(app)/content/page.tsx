import { FileText } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { PageHeader } from "@/components/common/page-header";

export default function ContentPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Content"
        description="Manage posts and pages across your WordPress sites."
      />
      <EmptyState
        icon={FileText}
        title="No content yet"
        description="Posts and pages from your connected sites will be listed here."
      />
    </div>
  );
}
