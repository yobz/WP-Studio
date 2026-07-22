import { AiAssistantPreview } from "@/features/dashboard/components/ai-assistant-preview";
import { AnalyticsPreview } from "@/features/dashboard/components/analytics-preview-lazy";
import { KpiCards } from "@/features/dashboard/components/kpi-cards";
import { QuickActions } from "@/features/dashboard/components/quick-actions";
import { RecentActivity } from "@/features/dashboard/components/recent-activity";
import { RecentDrafts } from "@/features/dashboard/components/recent-drafts";
import { SystemHealth } from "@/features/dashboard/components/system-health";
import { WelcomeSection } from "@/features/dashboard/components/welcome-section";
import { WordPressOverview } from "@/features/dashboard/components/wordpress-overview";

export default function DashboardPage() {
  return (
    <div className="flex flex-col gap-6">
      <WelcomeSection />
      <KpiCards />
      <QuickActions />

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <RecentActivity />
        <WordPressOverview />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <AnalyticsPreview />
        </div>
        <RecentDrafts />
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <AiAssistantPreview />
        <SystemHealth />
      </div>
    </div>
  );
}
