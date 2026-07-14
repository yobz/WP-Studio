import type { StatusBadgeStatus } from "@/components/common/status-badge";
import type {
  ServiceStatus,
  SiteStatus,
} from "@/features/dashboard/types/dashboard.types";

export const SITE_STATUS_META: Record<
  SiteStatus,
  { label: string; badge: StatusBadgeStatus }
> = {
  connected: { label: "Connected", badge: "success" },
  syncing: { label: "Syncing", badge: "warning" },
  disconnected: { label: "Disconnected", badge: "neutral" },
  error: { label: "Connection Error", badge: "error" },
};

export const SERVICE_STATUS_META: Record<
  ServiceStatus,
  { label: string; badge: StatusBadgeStatus }
> = {
  operational: { label: "Operational", badge: "success" },
  degraded: { label: "Degraded", badge: "warning" },
  down: { label: "Down", badge: "error" },
};
