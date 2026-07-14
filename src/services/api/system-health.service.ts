import { apiFetch } from "@/lib/api-client";
import type {
  ServiceStatus,
  SiteStatus,
} from "@/features/dashboard/types/dashboard.types";

export interface ApiSystemHealth {
  api_status: ServiceStatus;
  wordpress_connection: SiteStatus;
  storage_used_percent: number;
  background_queue: {
    pending: number;
    status: ServiceStatus;
  };
}

export async function getSystemHealth(): Promise<ApiSystemHealth> {
  return apiFetch<ApiSystemHealth>("/api/v1/system-health");
}
