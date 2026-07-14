import {
  BarChart3,
  FileText,
  Globe,
  Image,
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

export const navigation: NavGroup[] = [
  {
    items: [{ title: "Dashboard", href: "/dashboard", icon: LayoutDashboard }],
  },
  {
    label: "Manage",
    items: [
      { title: "Content", href: "/content", icon: FileText },
      { title: "WordPress", href: "/wordpress", icon: Globe },
      { title: "Media", href: "/media", icon: Image },
      { title: "Analytics", href: "/analytics", icon: BarChart3 },
    ],
  },
  {
    items: [{ title: "Settings", href: "/settings", icon: Settings }],
  },
];

const flatNavItems: NavItem[] = navigation.flatMap((group) => group.items);

export function getNavTitle(href: string): string {
  const match = flatNavItems.find((item) => item.href === href);
  if (match) return match.title;

  const segment = href.split("/").filter(Boolean).pop() ?? "";
  return segment.charAt(0).toUpperCase() + segment.slice(1);
}
