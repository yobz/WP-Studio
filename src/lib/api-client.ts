/**
 * Thin fetch wrapper for the Laravel API. Every real (non-mock)
 * service function goes through this, so the response envelope
 * (`{success, data}` / `{success: false, error}`) is unwrapped and
 * validated in exactly one place — see
 * backend/app/Http/Support/ApiResponse.php for the envelope this
 * mirrors, and docs/adr/0004-backend-foundation.md for the contract.
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

interface ApiSuccessEnvelope<T> {
  success: true;
  data: T;
  meta?: Record<string, unknown>;
}

interface ApiErrorEnvelope {
  success: false;
  error: {
    code: string;
    message: string;
    details?: Record<string, unknown>;
  };
  request_id?: string;
}

type ApiEnvelope<T> = ApiSuccessEnvelope<T> | ApiErrorEnvelope;

export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
    public readonly details?: Record<string, unknown>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function apiFetch<T>(
  path: string,
  init?: RequestInit,
): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...init?.headers,
    },
  });

  // The backend always returns the envelope shape, even on error
  // responses (4xx/5xx) — parsing JSON before checking `response.ok`
  // lets a single check below cover both "transport succeeded, API
  // reported an error" and "API succeeded."
  const body = (await response.json()) as ApiEnvelope<T>;

  if (!response.ok || !body.success) {
    const errorBody = body as ApiErrorEnvelope;
    throw new ApiError(
      errorBody.error?.code ?? "UNKNOWN_ERROR",
      errorBody.error?.message ?? "The request failed.",
      response.status,
      errorBody.error?.details,
    );
  }

  return body.data;
}
