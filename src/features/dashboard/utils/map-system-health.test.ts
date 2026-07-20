import { describe, expect, it } from "vitest";

import { mapSystemHealth } from "./map-system-health";
import type { GraphQLSystemHealth } from "@/features/dashboard/hooks/use-dashboard-overview";

const baseHealth: GraphQLSystemHealth = {
  apiStatus: "operational",
  wordpressConnection: "connected",
  storageUsedPercent: 42,
  queueDriver: "database",
  queuePending: 3,
  queueFailed: 1,
  queueOldestPendingSeconds: 120,
  queueStatus: "degraded",
};

describe("mapSystemHealth", () => {
  it("passes through top-level status fields unchanged", () => {
    const health = mapSystemHealth(baseHealth);

    expect(health.apiStatus).toBe("operational");
    expect(health.wordpressConnection).toBe("connected");
    expect(health.storageUsedPercent).toBe(42);
  });

  it("nests the flat queue fields into backgroundQueue", () => {
    const health = mapSystemHealth(baseHealth);

    expect(health.backgroundQueue).toEqual({
      driver: "database",
      pending: 3,
      failed: 1,
      oldestPendingSeconds: 120,
      status: "degraded",
    });
  });

  it("preserves a null pending count rather than coercing it to zero", () => {
    const health = mapSystemHealth({ ...baseHealth, queuePending: null });

    expect(health.backgroundQueue.pending).toBeNull();
  });
});
