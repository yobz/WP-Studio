import { API_BASE_URL, csrfHeader, UNAUTHORIZED_EVENT } from "@/lib/api-client";

interface GraphQLErrorEntry {
  message: string;
  path?: (string | number)[];
}

interface GraphQLEnvelope<T> {
  data: T | null;
  errors?: GraphQLErrorEntry[];
}

// auth:sanctum rejects an unauthenticated request before GraphQL ever
// executes, so that case comes back as the standard REST error envelope,
// not {data, errors} — handled explicitly below rather than assumed away.
interface RestErrorEnvelope {
  success: false;
  error: { code: string; message: string };
}

export class GraphQLRequestError extends Error {
  constructor(
    message: string,
    public readonly errors: GraphQLErrorEntry[],
  ) {
    super(message);
    this.name = "GraphQLRequestError";
  }
}

function notifyUnauthorized(): void {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
  }
}

/**
 * The one place that calls the GraphQL endpoint — mirrors apiFetch's
 * role for REST. Reuses the same CSRF/session handshake since GraphQL
 * sits behind the identical auth:sanctum + ResolveCurrentWorkspace
 * middleware stack as every REST route (see docs/adr/0011-graphql-layer.md).
 */
export async function graphqlFetch<T>(
  query: string,
  variables?: Record<string, unknown>,
): Promise<T> {
  const headers = await csrfHeader();

  const response = await fetch(`${API_BASE_URL}/api/v1/graphql`, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...headers,
    },
    body: JSON.stringify({ query, variables }),
  });

  const body = (await response.json()) as
    GraphQLEnvelope<T> | RestErrorEnvelope;

  if ("success" in body) {
    if (response.status === 401 && body.error.code === "UNAUTHENTICATED") {
      notifyUnauthorized();
    }
    throw new GraphQLRequestError(body.error.message, []);
  }

  if (!response.ok || body.errors?.length || body.data === null) {
    throw new GraphQLRequestError(
      body.errors?.[0]?.message ?? "The GraphQL request failed.",
      body.errors ?? [],
    );
  }

  return body.data;
}
