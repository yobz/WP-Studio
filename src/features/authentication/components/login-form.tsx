"use client";

import * as React from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Typography } from "@/components/ui/typography";
import { useLogin } from "@/features/authentication/hooks/use-auth";
import { ApiError } from "@/lib/api-client";

const loginSchema = z.object({
  email: z
    .string()
    .min(1, "Email is required")
    .email("Enter a valid email address"),
  password: z.string().min(1, "Password is required"),
});

type LoginFormValues = z.infer<typeof loginSchema>;

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const login = useLogin();
  const [formError, setFormError] = React.useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  });

  function onSubmit(values: LoginFormValues) {
    setFormError(null);

    login.mutate(values, {
      onSuccess: () => {
        const redirectTo = searchParams.get("redirect");
        router.replace(
          redirectTo && redirectTo.startsWith("/") ? redirectTo : "/dashboard",
        );
      },
      onError: (error) => {
        setFormError(
          error instanceof ApiError && error.code === "INVALID_CREDENTIALS"
            ? "Incorrect email or password."
            : "Something went wrong. Please try again.",
        );
      },
    });
  }

  return (
    <form
      onSubmit={handleSubmit(onSubmit)}
      noValidate
      className="flex flex-col gap-4"
    >
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="email">Email</Label>
        <Input
          id="email"
          type="email"
          autoComplete="email"
          aria-invalid={!!errors.email}
          {...register("email")}
        />
        {errors.email ? (
          <Typography variant="caption" className="text-destructive">
            {errors.email.message}
          </Typography>
        ) : null}
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="password">Password</Label>
        <Input
          id="password"
          type="password"
          autoComplete="current-password"
          aria-invalid={!!errors.password}
          {...register("password")}
        />
        {errors.password ? (
          <Typography variant="caption" className="text-destructive">
            {errors.password.message}
          </Typography>
        ) : null}
      </div>

      {formError ? (
        <Typography variant="body-sm" role="alert" className="text-destructive">
          {formError}
        </Typography>
      ) : null}

      <Button type="submit" loading={login.isPending} className="mt-2">
        Sign in
      </Button>
    </form>
  );
}

export { LoginForm };
