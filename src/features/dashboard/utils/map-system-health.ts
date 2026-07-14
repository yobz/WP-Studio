import type { ApiSystemHealth } from "@/services/api/system-health.service";
import type { SystemHealth } from "@/features/dashboard/types/dashboard.types";

export function mapSystemHealth(health: ApiSystemHealth): SystemHealth {
  return {
    apiStatus: health.api_status,
    wordpressConnection: health.wordpress_connection,
    storageUsedPercent: health.storage_used_percent,
    backgroundQueue: {
      pending: health.background_queue.pending,
      status: health.background_queue.status,
    },
  };
}
