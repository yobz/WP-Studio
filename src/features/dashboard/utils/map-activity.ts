import type { ApiActivityItem } from "@/services/api/dashboard.service";
import type { ActivityItem } from "@/features/dashboard/types/dashboard.types";

export function mapActivityItem(item: ApiActivityItem): ActivityItem {
  return {
    id: item.id,
    type: item.type,
    title: item.title,
    siteName: item.site_name,
    timestamp: item.timestamp,
  };
}
