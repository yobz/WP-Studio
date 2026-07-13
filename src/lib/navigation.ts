import {
  BarChart3,
  FileText,
  Globe,
  LayoutDashboard,
  Settings,
  type LucideIcon,
} from "lucide-react";

interface NavItem {
  title: string;
  href: string;
  icon: LucideIcon;
}

interface NavGroup {
  label?: string;
  items: NavItem[];
}

/**
 * Single source of truth for the sidebar and breadcrumbs. Adding a future
 * module (Laravel-backed feature, WordPress tooling, etc.) means adding an
 * entry here — AppSidebar and the breadcrumb resolver both read this
 * config and never need to change themselves.
 */
export const navigation: NavGroup[] = [
  {
    items: [{ title: "Dashboard", href: "/dashboard", icon: LayoutDashboard }],
  },
  {
    label: "Manage",
    items: [
      { title: "Content", href: "/content", icon: FileText },
      { title: "WordPress", href: "/wordpress", icon: Globe },
      { title: "Analytics", href: "/analytics", icon: BarChart3 },
    ],
  },
  {
    items: [{ title: "Settings", href: "/settings", icon: Settings }],
  },
];

const flatNavItems: NavItem[] = navigation.flatMap((group) => group.items);

/** Looks up a route's nav title for breadcrumbs; falls back to a naive
 * capitalized segment for routes not in the nav config (e.g. detail pages). */
export function getNavTitle(href: string): string {
  const match = flatNavItems.find((item) => item.href === href);
  if (match) return match.title;

  const segment = href.split("/").filter(Boolean).pop() ?? "";
  return segment.charAt(0).toUpperCase() + segment.slice(1);
}
