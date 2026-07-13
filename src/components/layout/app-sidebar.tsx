"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { HelpCircle, Layers } from "lucide-react";

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar";
import { Typography } from "@/components/ui/typography";
import { navigation } from "@/lib/navigation";

function AppSidebar() {
  const pathname = usePathname();

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              render={<Link href="/" />}
              className="gap-2"
            >
              <div className="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                <Layers className="size-4" />
              </div>
              <Typography as="span" variant="label" className="truncate">
                WP Studio
              </Typography>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {navigation.map((group, index) => (
          <SidebarGroup key={group.label ?? `group-${index}`}>
            {group.label ? (
              <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            ) : null}
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  // Exact match is correct for every route today (all
                  // top-level, one segment deep). Once nested/detail
                  // routes exist (e.g. a future `/content/[id]`), this
                  // will need to become prefix- or segment-aware so the
                  // parent nav item still highlights — see
                  // docs/adr/0002-product-shell.md. Not changed now:
                  // there's no nested route to test it against yet, and
                  // guessing the right matching rule ahead of a real
                  // need risks getting it wrong.
                  const isActive = pathname === item.href;
                  return (
                    <SidebarMenuItem key={item.href}>
                      <SidebarMenuButton
                        isActive={isActive}
                        tooltip={item.title}
                        render={<Link href={item.href} />}
                      >
                        <item.icon />
                        <span>{item.title}</span>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>

      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              tooltip="Help & Support"
              aria-disabled="true"
              render={
                <a href="#" onClick={(event) => event.preventDefault()} />
              }
            >
              <HelpCircle />
              <span>Help & Support</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  );
}

export { AppSidebar };
