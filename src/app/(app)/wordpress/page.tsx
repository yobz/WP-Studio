import { PageHeader } from "@/components/common/page-header";
import { ConnectSiteDialog } from "@/features/wordpress/components/connect-site-dialog";
import { SitesList } from "@/features/wordpress/components/sites-list";

export default function WordPressPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="WordPress"
        description="Connect and manage your WordPress site integrations."
        actions={<ConnectSiteDialog />}
      />
      <SitesList />
    </div>
  );
}
