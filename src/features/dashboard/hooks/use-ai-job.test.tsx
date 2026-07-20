import * as React from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useAiJob } from "./use-ai-job";

vi.mock("@/services/api/ai.service", () => ({
  getAiJob: vi.fn(),
}));

const { getAiJob } = await import("@/services/api/ai.service");

function wrapper({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
}

describe("useAiJob", () => {
  beforeEach(() => {
    vi.mocked(getAiJob).mockReset();
  });

  it("stays disabled and never calls the API when id is null", () => {
    const { result } = renderHook(() => useAiJob(null), { wrapper });

    expect(result.current.fetchStatus).toBe("idle");
    expect(getAiJob).not.toHaveBeenCalled();
  });

  it("fetches the job once an id is provided", async () => {
    vi.mocked(getAiJob).mockResolvedValueOnce({
      id: 7,
      status: "completed",
      prompt: "Write a post",
      result: "Draft.",
      error_message: null,
      model: "claude-opus-4-8",
      input_tokens: 1,
      output_tokens: 2,
      attempted_at: null,
      completed_at: null,
      created_at: null,
    });

    const { result } = renderHook(() => useAiJob(7), { wrapper });

    await waitFor(() => expect(result.current.data?.status).toBe("completed"));
    expect(getAiJob).toHaveBeenCalledWith(7);
    expect(getAiJob).toHaveBeenCalledTimes(1);
  });
});
