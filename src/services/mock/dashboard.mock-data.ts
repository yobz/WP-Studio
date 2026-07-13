import type {
  ActivityItem,
  AnalyticsPoint,
  Draft,
  QuickAction,
  SystemHealth,
} from "@/features/dashboard/types/dashboard.types";

const HOUR = 60 * 60 * 1000;
const DAY = 24 * HOUR;
const hoursAgo = (n: number) => new Date(Date.now() - n * HOUR).toISOString();
const daysAgo = (n: number) => new Date(Date.now() - n * DAY).toISOString();

// KPI mock data removed — KPI Cards now reads from the real Laravel
// endpoint (src/services/api/dashboard.service.ts). See
// docs/adr/0004-backend-foundation.md.

export const mockQuickActions: QuickAction[] = [
  {
    id: "new-post",
    title: "New Post",
    description: "Start writing a new post for one of your sites.",
  },
  {
    id: "generate-ai-draft",
    title: "Generate AI Draft",
    description: "Let AI draft a starting point from a prompt.",
  },
  {
    id: "connect-site",
    title: "Connect WordPress Site",
    description: "Link another WordPress install to your workspace.",
  },
  {
    id: "view-analytics",
    title: "View Analytics",
    description: "See traffic and engagement across your sites.",
  },
];

export const mockActivity: ActivityItem[] = [
  {
    id: "act-1",
    type: "post-published",
    title: "10 SEO Tips for 2026",
    siteName: "Acme Blog",
    timestamp: hoursAgo(2),
  },
  {
    id: "act-2",
    type: "draft-created",
    title: "Q3 Product Roadmap",
    siteName: "Acme Blog",
    timestamp: hoursAgo(5),
  },
  {
    id: "act-3",
    type: "plugin-updated",
    title: "Yoast SEO updated to 22.1",
    siteName: "Acme Blog",
    timestamp: daysAgo(1),
  },
  {
    id: "act-4",
    type: "ai-draft-generated",
    title: "Holiday Marketing Ideas",
    siteName: "Acme Blog",
    timestamp: daysAgo(1),
  },
  {
    id: "act-5",
    type: "post-published",
    title: "Behind the Scenes: Our Design Process",
    siteName: "Portfolio Site",
    timestamp: daysAgo(2),
  },
];

function buildAnalyticsSeries(days: number): AnalyticsPoint[] {
  const points: AnalyticsPoint[] = [];
  let visitors = 480;
  for (let i = days - 1; i >= 0; i--) {
    // Gentle upward drift with day-to-day noise, clamped to stay positive —
    // reads as a believable real traffic trend rather than a straight line.
    visitors = Math.max(
      120,
      Math.round(visitors + (Math.random() - 0.35) * 60),
    );
    const date = new Date(Date.now() - i * DAY);
    points.push({
      date: date.toISOString().slice(0, 10),
      visitors,
      postsPublished: Math.random() > 0.7 ? 1 : 0,
    });
  }
  return points;
}

export const mockAnalyticsByRange: Record<
  "7d" | "30d" | "90d",
  AnalyticsPoint[]
> = {
  "7d": buildAnalyticsSeries(7),
  "30d": buildAnalyticsSeries(30),
  "90d": buildAnalyticsSeries(90),
};

export const mockDrafts: Draft[] = [
  {
    id: "draft-1",
    title: "Q3 Product Roadmap",
    siteName: "Acme Blog",
    status: "draft",
    updatedAt: hoursAgo(5),
  },
  {
    id: "draft-2",
    title: "Holiday Marketing Ideas",
    siteName: "Acme Blog",
    status: "in-review",
    updatedAt: daysAgo(1),
  },
  {
    id: "draft-3",
    title: "Customer Success Stories",
    siteName: "Portfolio Site",
    status: "draft",
    updatedAt: daysAgo(3),
  },
  {
    id: "draft-4",
    title: "2026 Feature Preview",
    siteName: "Acme Blog",
    status: "draft",
    updatedAt: daysAgo(4),
  },
];

export const mockSystemHealth: SystemHealth = {
  apiStatus: "operational",
  wordpressConnection: "connected",
  storageUsedPercent: 24,
  backgroundQueue: { pending: 2, status: "operational" },
};
