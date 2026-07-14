import { notFound } from "next/navigation";

import { SiteDetail } from "@/features/wordpress/components/site-detail";

interface WordPressSitePageProps {
  params: Promise<{ id: string }>;
}

export default async function WordPressSitePage({
  params,
}: WordPressSitePageProps) {
  const { id } = await params;
  const siteId = Number(id);

  if (!Number.isInteger(siteId) || siteId <= 0) {
    notFound();
  }

  return <SiteDetail siteId={siteId} />;
}
