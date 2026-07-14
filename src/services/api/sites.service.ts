import { apiFetch } from "@/lib/api-client";

export interface ApiSite {
  id: number;
  workspace_id: number;
  name: string;
  url: string | null;
  status: "connected" | "syncing" | "disconnected" | "error";
  wordpress_version: string | null;
  theme: string | null;
  php_version: string | null;
  plugin_updates_available: number;
  plugin_count: number | null;
  user_count: number | null;
  timezone: string | null;
  language: string | null;
  storage_used_mb: number;
  storage_limit_mb: number;
  last_connected_at: string | null;
  last_checked_at: string | null;
  last_synced_at: string | null;
  connection_error: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ConnectSitePayload {
  name: string;
  url: string;
  wp_username: string;
  application_password: string;
}

export interface SyncResult {
  content_type: string;
  created: number;
  updated: number;
  skipped: number;
  failed: number;
  errors: { wordpress_id: number | null; message: string }[];
  started_at: string;
  finished_at: string;
}

export interface SyncStatistics {
  content_type: string;
  total_synced: number;
  last_synced_at: string | null;
  site_status: ApiSite["status"];
  connection_error: string | null;
}

export async function getSites(params?: {
  status?: ApiSite["status"];
}): Promise<ApiSite[]> {
  const query = params?.status ? `?status=${params.status}` : "";
  return apiFetch<ApiSite[]>(`/api/v1/sites${query}`);
}

export async function getSite(id: number): Promise<ApiSite> {
  return apiFetch<ApiSite>(`/api/v1/sites/${id}`);
}

export async function connectSite(
  payload: ConnectSitePayload,
): Promise<ApiSite> {
  return apiFetch<ApiSite>("/api/v1/sites", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function disconnectSite(id: number): Promise<ApiSite> {
  return apiFetch<ApiSite>(`/api/v1/sites/${id}/disconnect`, {
    method: "POST",
  });
}

export async function verifySiteConnection(id: number): Promise<ApiSite> {
  return apiFetch<ApiSite>(`/api/v1/sites/${id}/verify`, {
    method: "POST",
  });
}

export async function refreshSiteMetadata(id: number): Promise<ApiSite> {
  return apiFetch<ApiSite>(`/api/v1/sites/${id}/refresh-metadata`, {
    method: "POST",
  });
}

export async function deleteSite(id: number): Promise<void> {
  await apiFetch<null>(`/api/v1/sites/${id}`, { method: "DELETE" });
}

export async function syncSite(id: number): Promise<SyncResult> {
  return apiFetch<SyncResult>(`/api/v1/sites/${id}/sync`, {
    method: "POST",
  });
}

export async function getSyncStatus(id: number): Promise<SyncStatistics> {
  return apiFetch<SyncStatistics>(`/api/v1/sites/${id}/sync-status`);
}
