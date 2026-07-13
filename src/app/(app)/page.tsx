import { Layers } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { PageHeader } from "@/components/common/page-header";

export default function HomePage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Overview"
        description="A single home base for every WordPress site you manage."
      />
      <EmptyState
        icon={Layers}
        title="Nothing here yet"
        description="This overview will surface your connected sites and recent activity once those modules exist."
      />
    </div>
  );
}
