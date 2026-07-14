import { apiFetch } from "@/lib/api-client";
import type {
  AuthUser,
  LoginCredentials,
} from "@/features/authentication/types/auth.types";

export async function login(credentials: LoginCredentials): Promise<AuthUser> {
  return apiFetch<AuthUser>("/api/v1/login", {
    method: "POST",
    body: JSON.stringify(credentials),
  });
}

export async function logout(): Promise<void> {
  await apiFetch<null>("/api/v1/logout", { method: "POST" });
}

export async function getCurrentUser(): Promise<AuthUser> {
  return apiFetch<AuthUser>("/api/v1/user");
}
