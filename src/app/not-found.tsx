import Link from "next/link";
import { Compass } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { Button } from "@/components/ui/button";

export default function NotFound() {
  return (
    <main className="flex flex-1 items-center justify-center p-6">
      <div className="w-full max-w-md">
        <EmptyState
          icon={Compass}
          title="Page not found"
          titleAs="h1"
          description="The page you're looking for doesn't exist or may have moved."
          action={
            <Button render={<Link href="/" />} nativeButton={false}>
              Back to Overview
            </Button>
          }
        />
      </div>
    </main>
  );
}
