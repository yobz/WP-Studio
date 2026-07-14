import type { Metadata } from "next";
import { Suspense } from "react";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Typography } from "@/components/ui/typography";
import { LoginForm } from "@/features/authentication/components/login-form";

export const metadata: Metadata = {
  title: "Sign in — WP Studio",
};

export default function LoginPage() {
  return (
    <Card className="w-full max-w-sm">
      <CardHeader>
        <CardTitle>
          <Typography as="h1" variant="h3">
            Sign in
          </Typography>
        </CardTitle>
        <CardDescription>
          Sign in to manage your WordPress sites.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Suspense fallback={<Skeleton className="h-48 w-full" />}>
          <LoginForm />
        </Suspense>
      </CardContent>
    </Card>
  );
}
