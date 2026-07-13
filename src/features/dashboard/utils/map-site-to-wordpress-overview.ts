import type { ApiSite } from "@/services/api/sites.service";
import type { WordPressOverview } from "@/features/dashboard/types/dashboard.types";

/**
 * Adapts the real API's `Site` shape into the exact `WordPressOverview`
 * shape `WordPressOverview` (the widget) already renders — same
 * pattern as map-summary-to-kpis.ts. See
 * docs/adr/0005-domain-model.md.
 */
export function mapSiteToWordPressOverview(site: ApiSite): WordPressOverview {
  return {
    siteName: site.name,
    status: site.status,
    wordpressVersion: site.wordpress_version ?? "Unknown",
    theme: site.theme ?? "Unknown",
    pluginUpdatesAvailable: site.plugin_updates_available,
  };
}
