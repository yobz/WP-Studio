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

export const UNAUTHORIZED_EVENT = "wp-studio:unauthorized";

function notifyUnauthorized(): void {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event(UNAUTHORIZED_EVENT));
  }
}

const MUTATING_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

let csrfCookieRequest: Promise<void> | null = null;

async function ensureCsrfCookie(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;

  csrfCookieRequest ??= fetch(`${API_BASE_URL}/sanctum/csrf-cookie`, {
    credentials: "include",
  }).then(() => undefined);

  try {
    await csrfCookieRequest;
  } finally {
    csrfCookieRequest = null;
  }
}

async function parseEnvelope<T>(response: Response): Promise<T> {
  const body = (await response.json()) as ApiEnvelope<T>;

  if (!response.ok || !body.success) {
    const errorBody = body as ApiErrorEnvelope;

    if (
      response.status === 401 &&
      errorBody.error?.code === "UNAUTHENTICATED"
    ) {
      notifyUnauthorized();
    }

    throw new ApiError(
      errorBody.error?.code ?? "UNKNOWN_ERROR",
      errorBody.error?.message ?? "The request failed.",
      response.status,
      errorBody.error?.details,
    );
  }

  return body.data;
}

export async function apiFetch<T>(
  path: string,
  init?: RequestInit,
): Promise<T> {
  const method = (init?.method ?? "GET").toUpperCase();

  if (MUTATING_METHODS.has(method)) {
    await ensureCsrfCookie();
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(readCookie("XSRF-TOKEN")
        ? { "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") as string }
        : {}),
      ...init?.headers,
    },
  });

  return parseEnvelope<T>(response);
}

/**
 * Like apiFetch, but for multipart file uploads — omits the JSON
 * Content-Type so the browser sets the multipart boundary itself.
 */
export async function apiUpload<T>(
  path: string,
  formData: FormData,
): Promise<T> {
  await ensureCsrfCookie();

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: "POST",
    credentials: "include",
    body: formData,
    headers: {
      Accept: "application/json",
      ...(readCookie("XSRF-TOKEN")
        ? { "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") as string }
        : {}),
    },
  });

  return parseEnvelope<T>(response);
}
