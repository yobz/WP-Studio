import { create } from "zustand";

interface NotificationState {
  count: number;
  setCount: (count: number) => void;
  clear: () => void;
}

/**
 * Global, not feature-scoped, because the two things that touch it —
 * `AppHeader` (renders the badge) and the dashboard's activity query (sets
 * the count once data loads) — sit in unrelated component trees with no
 * shared ancestor worth lifting state into. Everything else on the
 * dashboard uses local component state; this is the one piece of UI state
 * genuinely cross-cutting enough to warrant Zustand (see
 * docs/adr/0003-dashboard-data-architecture.md).
 */
export const useNotificationStore = create<NotificationState>((set) => ({
  count: 0,
  setCount: (count) => set({ count }),
  clear: () => set({ count: 0 }),
}));
