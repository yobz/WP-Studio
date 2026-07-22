import { apiFetch, apiFetchWithMeta } from "@/lib/api-client";
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

export interface PostsPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PostsPage {
  posts: ApiPost[];
  pagination: PostsPagination;
}

async function fetchPostsPage(path: string): Promise<PostsPage> {
  const { data, meta } = await apiFetchWithMeta<ApiPost[]>(path);

  return { posts: data, pagination: meta.pagination as PostsPagination };
}

export async function getSitePosts(
  siteId: number,
  page = 1,
): Promise<PostsPage> {
  return fetchPostsPage(`/api/v1/posts?site_id=${siteId}&page=${page}`);
}

export async function getPost(id: number): Promise<ApiPost> {
  return apiFetch<ApiPost>(`/api/v1/posts/${id}`);
}

const RECENT_DRAFTS_LIMIT = 5;

export async function getRecentDrafts(): Promise<ApiPost[]> {
  const { posts } = await fetchPostsPage(
    `/api/v1/posts?status=unpublished&per_page=${RECENT_DRAFTS_LIMIT}`,
  );

  return posts;
}
