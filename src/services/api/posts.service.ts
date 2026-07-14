import { apiFetch } from "@/lib/api-client";
import type { ApiMedia } from "@/services/api/media.service";

export interface ApiPost {
  id: number;
  site_id: number;
  site_name: string;
  title: string;
  status: "draft" | "in-review" | "published";
  published_at: string | null;
  wordpress_post_id: number | null;
  wordpress_modified_at: string | null;
  wordpress_url: string | null;
  sync_status: string | null;
  last_synced_at: string | null;
  featured_image: ApiMedia | null;
  created_at: string | null;
  updated_at: string | null;
}

export async function getSitePosts(siteId: number): Promise<ApiPost[]> {
  return apiFetch<ApiPost[]>(`/api/v1/posts?site_id=${siteId}`);
}

export async function getPost(id: number): Promise<ApiPost> {
  return apiFetch<ApiPost>(`/api/v1/posts/${id}`);
}

export async function getRecentDrafts(): Promise<ApiPost[]> {
  return apiFetch<ApiPost[]>("/api/v1/posts?status=unpublished");
}
