"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Bell, Search, X } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { SearchInput } from "@/components/common/search-input";
import { ThemeToggle } from "@/components/common/theme-toggle";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  useCurrentUser,
  useLogout,
} from "@/features/authentication/hooks/use-auth";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { Typography } from "@/components/ui/typography";
import { getNavTitle } from "@/lib/navigation";
import { useNotificationStore } from "@/store/notification-store";

function AppHeader() {
  const router = useRouter();
  const pathname = usePathname();
  const segments = pathname.split("/").filter(Boolean);
  const [searchValue, setSearchValue] = React.useState("");
  const [mobileSearchOpen, setMobileSearchOpen] = React.useState(false);
  const notificationCount = useNotificationStore((state) => state.count);
  const clearNotifications = useNotificationStore((state) => state.clear);
  const { data: user } = useCurrentUser();
  const logout = useLogout();

  function handleSignOut() {
    logout.mutate(undefined, {
      onSuccess: () => router.replace("/login"),
    });
  }

  const initial = user?.name.charAt(0).toUpperCase() ?? "U";

  return (
    <header className="border-border flex h-14 shrink-0 items-center gap-3 border-b px-4">
      {mobileSearchOpen ? (
        <div className="flex flex-1 items-center gap-2 sm:hidden">
          <SearchInput
            autoFocus
            placeholder="Search..."
            value={searchValue}
            onChange={(event) => setSearchValue(event.target.value)}
            onClear={() => setSearchValue("")}
            className="flex-1"
          />
          <Button
            variant="ghost"
            size="icon-sm"
            aria-label="Close search"
            onClick={() => setMobileSearchOpen(false)}
          >
            <X />
          </Button>
        </div>
      ) : (
        <>
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-5" />

          <Breadcrumb className="min-w-0 flex-1">
            <BreadcrumbList className="flex-nowrap">
              <BreadcrumbItem>
                <BreadcrumbLink render={<Link href="/" />}>Home</BreadcrumbLink>
              </BreadcrumbItem>
              {segments.map((_segment, index) => {
                const href = "/" + segments.slice(0, index + 1).join("/");
                const isLast = index === segments.length - 1;
                return (
                  <React.Fragment key={href}>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                      {isLast ? (
                        <BreadcrumbPage>{getNavTitle(href)}</BreadcrumbPage>
                      ) : (
                        <BreadcrumbLink render={<Link href={href} />}>
                          {getNavTitle(href)}
                        </BreadcrumbLink>
                      )}
                    </BreadcrumbItem>
                  </React.Fragment>
                );
              })}
            </BreadcrumbList>
          </Breadcrumb>

          <div className="hidden w-full max-w-56 sm:block">
            <SearchInput
              placeholder="Search..."
              value={searchValue}
              onChange={(event) => setSearchValue(event.target.value)}
              onClear={() => setSearchValue("")}
            />
          </div>

          <Button
            variant="ghost"
            size="icon-sm"
            aria-label="Search"
            className="sm:hidden"
            onClick={() => setMobileSearchOpen(true)}
          >
            <Search />
          </Button>

          <Popover>
            <PopoverTrigger
              render={
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label="Notifications"
                  className="relative"
                />
              }
            >
              <Bell />
              {notificationCount > 0 ? (
                <span
                  aria-hidden="true"
                  className="bg-primary text-primary-foreground absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full text-[10px] font-medium"
                >
                  {notificationCount > 9 ? "9+" : notificationCount}
                </span>
              ) : null}
            </PopoverTrigger>
            <PopoverContent align="end">
              {notificationCount > 0 ? (
                <div className="flex flex-col gap-3 py-1">
                  <div className="flex flex-col gap-0.5">
                    <Typography variant="label">Notifications</Typography>
                    <Typography
                      variant="body-sm"
                      className="text-muted-foreground"
                    >
                      You have {notificationCount} update
                      {notificationCount === 1 ? "" : "s"} from your dashboard
                      activity.
                    </Typography>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={clearNotifications}
                  >
                    Mark all as read
                  </Button>
                </div>
              ) : (
                <EmptyState
                  icon={Bell}
                  title="No notifications"
                  description="You're all caught up."
                  className="border-none p-0 py-2"
                />
              )}
            </PopoverContent>
          </Popover>

          <ThemeToggle />

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label="Open user menu"
                  className="rounded-full"
                />
              }
            >
              <Avatar size="sm">
                <AvatarFallback>{initial}</AvatarFallback>
              </Avatar>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuLabel>
                  {user?.email ?? "My Account"}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled>Profile</DropdownMenuItem>
                <DropdownMenuItem render={<Link href="/settings" />}>
                  Settings
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  disabled={logout.isPending}
                  onClick={handleSignOut}
                >
                  Sign out
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </>
      )}
    </header>
  );
}

export { AppHeader };
