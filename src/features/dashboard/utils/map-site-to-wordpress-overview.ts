import type { ApiSite } from "@/services/api/sites.service";
import type { WordPressOverview } from "@/features/dashboard/types/dashboard.types";

export function mapSiteToWordPressOverview(site: ApiSite): WordPressOverview {
  return {
    siteName: site.name,
    status: site.status,
    wordpressVersion: site.wordpress_version ?? "Unknown",
    theme: site.theme ?? "Unknown",
    pluginUpdatesAvailable: site.plugin_updates_available,
  };
}
