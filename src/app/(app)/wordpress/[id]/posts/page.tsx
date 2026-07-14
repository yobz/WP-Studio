import { notFound } from "next/navigation";

import { PageHeader } from "@/components/common/page-header";
import { PostsTable } from "@/features/wordpress/components/posts-table";

interface SitePostsPageProps {
  params: Promise<{ id: string }>;
}

export default async function SitePostsPage({ params }: SitePostsPageProps) {
  const { id } = await params;
  const siteId = Number(id);

  if (!Number.isInteger(siteId) || siteId <= 0) {
    notFound();
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Posts"
        description="Posts synced from this WordPress site."
      />
      <PostsTable siteId={siteId} />
    </div>
  );
}
