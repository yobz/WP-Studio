import { notFound } from "next/navigation";

import { PostDetail } from "@/features/wordpress/components/post-detail";

interface SitePostPageProps {
  params: Promise<{ id: string; postId: string }>;
}

export default async function SitePostPage({ params }: SitePostPageProps) {
  const { postId } = await params;
  const parsedPostId = Number(postId);

  if (!Number.isInteger(parsedPostId) || parsedPostId <= 0) {
    notFound();
  }

  return <PostDetail postId={parsedPostId} />;
}
