"use client";

import * as React from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import dynamic from "next/dynamic";

// Code-split and dev-only: the devtools panel should never reach a
// production bundle, and `ssr: false` keeps it out of the server render
// entirely (it's a browser-only debugging UI).
const ReactQueryDevtools = dynamic(
  () =>
    import("@tanstack/react-query-devtools").then(
      (mod) => mod.ReactQueryDevtools,
    ),
  { ssr: false },
);

interface QueryProviderProps {
  children: React.ReactNode;
}

function QueryProvider({ children }: QueryProviderProps) {
  // Created in useState (not module scope) so each browser tab/request
  // gets its own client — sharing one across requests would leak cached
  // data between unrelated sessions in an SSR context.
  const [queryClient] = React.useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            // Data is considered fresh for 60s — revisiting the dashboard
            // within that window reuses the cache instead of refetching,
            // the core behavior this milestone needs to demonstrate.
            staleTime: 60 * 1000,
            retry: 2,
            retryDelay: (attempt) => Math.min(1000 * 2 ** attempt, 10_000),
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      {process.env.NODE_ENV === "development" ? (
        <ReactQueryDevtools initialIsOpen={false} />
      ) : null}
    </QueryClientProvider>
  );
}

export { QueryProvider };
