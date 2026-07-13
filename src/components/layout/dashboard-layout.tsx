import type * as React from "react";

import { AppHeader } from "@/components/layout/app-header";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";

interface DashboardLayoutProps {
  children: React.ReactNode;
}

/**
 * The application shell: collapsible sidebar + header + content region.
 * Reused by every module route via the `(app)` route group layout — new
 * modules get this shell for free by placing a page under that group.
 */
function DashboardLayout({ children }: DashboardLayoutProps) {
  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <AppHeader />
        {/*
          `SidebarInset` already renders the page's <main> landmark —
          this is a plain <div>, not <main>, to avoid nesting two
          <main> elements (invalid per the HTML spec, and a real
          landmark-navigation defect for screen reader users).
        */}
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
          <div className="mx-auto w-full max-w-7xl">{children}</div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}

export { DashboardLayout };
