import { Settings } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { PageHeader } from "@/components/common/page-header";

export default function SettingsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Settings"
        description="Manage your account, workspace, and preferences."
      />
      <EmptyState
        icon={Settings}
        title="No settings yet"
        description="Account and workspace settings will live here."
      />
    </div>
  );
}
