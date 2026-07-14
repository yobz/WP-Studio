"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import {
  getCurrentUser,
  login,
  logout,
} from "@/features/authentication/services/auth.service";
import type {
  AuthUser,
  LoginCredentials,
} from "@/features/authentication/types/auth.types";
import { ApiError, UNAUTHORIZED_EVENT } from "@/lib/api-client";

export const authUserQueryKey = ["auth", "user"] as const;

export function useCurrentUser() {
  return useQuery<AuthUser | null>({
    queryKey: authUserQueryKey,
    queryFn: async () => {
      try {
        return await getCurrentUser();
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    retry: false,
    staleTime: 5 * 60 * 1000,
  });
}

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (credentials: LoginCredentials) => login(credentials),
    onSuccess: (user) => {
      queryClient.setQueryData(authUserQueryKey, user);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      queryClient.setQueryData(authUserQueryKey, null);
    },
  });
}

export function useUnauthorizedListener() {
  const queryClient = useQueryClient();

  React.useEffect(() => {
    function handleUnauthorized() {
      queryClient.setQueryData(authUserQueryKey, null);
    }

    window.addEventListener(UNAUTHORIZED_EVENT, handleUnauthorized);
    return () =>
      window.removeEventListener(UNAUTHORIZED_EVENT, handleUnauthorized);
  }, [queryClient]);
}
