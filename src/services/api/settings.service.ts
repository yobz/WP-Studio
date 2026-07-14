import { apiFetch } from "@/lib/api-client";

export interface ApiSettings {
  workspace: {
    name: string;
    slug: string;
    member_count: number;
  };
  user: {
    name: string;
    email: string;
    role: string | null;
  };
}

export async function getSettings(): Promise<ApiSettings> {
  return apiFetch<ApiSettings>("/api/v1/settings");
}
