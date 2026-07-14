import type { ApiPost } from "@/services/api/posts.service";
import type {
  Draft,
  DraftStatus,
} from "@/features/dashboard/types/dashboard.types";

export function mapPostToDraft(post: ApiPost): Draft {
  return {
    id: String(post.id),
    title: post.title,
    siteName: post.site_name,
    status: post.status as DraftStatus,
    updatedAt: post.updated_at ?? post.created_at ?? new Date().toISOString(),
  };
}
