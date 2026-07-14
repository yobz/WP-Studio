import type { ApiSystemHealth } from "@/services/api/system-health.service";
import type { SystemHealth } from "@/features/dashboard/types/dashboard.types";

export function mapSystemHealth(health: ApiSystemHealth): SystemHealth {
  return {
    apiStatus: health.api_status,
    wordpressConnection: health.wordpress_connection,
    storageUsedPercent: health.storage_used_percent,
    backgroundQueue: {
      driver: health.background_queue.driver,
      pending: health.background_queue.pending,
      failed: health.background_queue.failed,
      oldestPendingSeconds: health.background_queue.oldest_pending_seconds,
      status: health.background_queue.status,
    },
  };
}
