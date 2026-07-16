import type { GraphQLSystemHealth } from "@/features/dashboard/hooks/use-dashboard-overview";
import type {
  ServiceStatus,
  SiteStatus,
  SystemHealth,
} from "@/features/dashboard/types/dashboard.types";

export function mapSystemHealth(health: GraphQLSystemHealth): SystemHealth {
  return {
    apiStatus: health.apiStatus as ServiceStatus,
    wordpressConnection: health.wordpressConnection as SiteStatus,
    storageUsedPercent: health.storageUsedPercent,
    backgroundQueue: {
      driver: health.queueDriver,
      pending: health.queuePending,
      failed: health.queueFailed,
      oldestPendingSeconds: health.queueOldestPendingSeconds,
      status: health.queueStatus as ServiceStatus,
    },
  };
}
