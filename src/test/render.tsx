import * as React from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, type RenderOptions } from "@testing-library/react";

/**
 * Renders with a fresh QueryClient per call, retries disabled so a
 * mocked failure resolves immediately instead of waiting out the app's
 * production retry/backoff (src/components/common/query-provider.tsx).
 */
export function renderWithQueryClient(
  ui: React.ReactElement,
  options?: RenderOptions,
) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>,
    options,
  );
}
