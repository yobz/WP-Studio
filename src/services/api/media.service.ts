import { apiFetch, apiUpload } from "@/lib/api-client";

export type ApiMediaSource = "upload" | "wordpress";

export interface ApiMedia {
  id: number;
  workspace_id: number;
  site_id: number | null;
  mediable_type: string | null;
  mediable_id: number | null;
  collection: string | null;
  source: ApiMediaSource;
  url: string;
  filename: string;
  extension: string;
  mime_type: string;
  size: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface MediaListFilters {
  source?: ApiMediaSource;
  mime_type?: string;
}

export async function getMedia(
  filters: MediaListFilters = {},
): Promise<ApiMedia[]> {
  const params = new URLSearchParams();
  if (filters.source) params.set("source", filters.source);
  if (filters.mime_type) params.set("mime_type", filters.mime_type);

  const query = params.toString();
  return apiFetch<ApiMedia[]>(`/api/v1/media${query ? `?${query}` : ""}`);
}

export async function getMediaItem(id: number): Promise<ApiMedia> {
  return apiFetch<ApiMedia>(`/api/v1/media/${id}`);
}

export async function uploadMedia(
  file: File,
  altText?: string,
): Promise<ApiMedia> {
  const formData = new FormData();
  formData.set("file", file);
  if (altText) formData.set("alt_text", altText);

  return apiUpload<ApiMedia>("/api/v1/media", formData);
}

export async function updateMedia(
  id: number,
  altText: string,
): Promise<ApiMedia> {
  return apiFetch<ApiMedia>(`/api/v1/media/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ alt_text: altText }),
  });
}

export async function deleteMedia(id: number): Promise<null> {
  return apiFetch<null>(`/api/v1/media/${id}`, { method: "DELETE" });
}
