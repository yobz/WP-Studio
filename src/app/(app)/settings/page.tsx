import { PageHeader } from "@/components/common/page-header";
import { SettingsSummary } from "@/features/settings/components/settings-summary";

export default function SettingsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Settings"
        description="Your workspace and account at a glance. Editable preferences are coming in a future update."
      />
      <SettingsSummary />
    </div>
  );
}
