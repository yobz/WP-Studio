import { apiFetch } from "@/lib/api-client";

/**
 * Mirrors backend/app/Http/Resources/V1/SiteResource.php.
 */
export interface ApiSite {
  id: number;
  workspace_id: number;
  name: string;
  status: "connected" | "syncing" | "disconnected";
  wordpress_version: string | null;
  theme: string | null;
  plugin_updates_available: number;
  storage_used_mb: number;
  storage_limit_mb: number;
  created_at: string | null;
  updated_at: string | null;
}

export async function getSites(params?: {
  status?: ApiSite["status"];
}): Promise<ApiSite[]> {
  const query = params?.status ? `?status=${params.status}` : "";
  return apiFetch<ApiSite[]>(`/api/v1/sites${query}`);
}
