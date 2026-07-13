import { create } from "zustand";

interface NotificationState {
  count: number;
  setCount: (count: number) => void;
  clear: () => void;
}

/**
 * Global, not feature-scoped, because the two things that touch it —
 * `AppHeader` (renders the badge) and `RecentActivity` (sets the count
 * whenever its query data changes, see
 * src/features/dashboard/components/recent-activity.tsx) — sit in
 * unrelated component trees with no shared ancestor worth lifting state
 * into. Everything else on the dashboard uses local component state;
 * this is the one piece of UI state genuinely cross-cutting enough to
 * warrant Zustand (see docs/adr/0003-dashboard-data-architecture.md).
 *
 * `count` currently just mirrors "how many activity items exist," not
 * genuine unread tracking — there's no backend yet to persist a
 * dismissed/read state, so `clear()` resets to 0 but a later activity
 * refetch will set it right back. Real read/unread state is a future
 * milestone's problem, once notifications are backed by something
 * persistent.
 */
export const useNotificationStore = create<NotificationState>((set) => ({
  count: 0,
  setCount: (count) => set({ count }),
  clear: () => set({ count: 0 }),
}));
