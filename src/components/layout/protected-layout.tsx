"use client";

import * as React from "react";
import { usePathname, useRouter } from "next/navigation";

import { LoadingState } from "@/components/common/loading-state";
import {
  useCurrentUser,
  useUnauthorizedListener,
} from "@/features/authentication/hooks/use-auth";

interface ProtectedLayoutProps {
  children: React.ReactNode;
}

function ProtectedLayout({ children }: ProtectedLayoutProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { data: user, isPending } = useCurrentUser();
  useUnauthorizedListener();

  React.useEffect(() => {
    if (isPending || user !== null) return;

    const redirectTarget =
      pathname && pathname !== "/dashboard"
        ? `?redirect=${encodeURIComponent(pathname)}`
        : "";
    router.replace(`/login${redirectTarget}`);
  }, [isPending, user, pathname, router]);

  if (isPending) {
    return (
      <LoadingState message="Checking your session..." className="min-h-svh" />
    );
  }

  if (user === null) {
    return null;
  }

  return children;
}

export { ProtectedLayout };
