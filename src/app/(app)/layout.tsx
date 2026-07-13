import type * as React from "react";

import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { ProtectedLayout } from "@/components/layout/protected-layout";

interface AppGroupLayoutProps {
  children: React.ReactNode;
}

export default function AppGroupLayout({ children }: AppGroupLayoutProps) {
  return (
    <ProtectedLayout>
      <DashboardLayout>{children}</DashboardLayout>
    </ProtectedLayout>
  );
}
