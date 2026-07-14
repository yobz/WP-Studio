import type * as React from "react";

interface AuthGroupLayoutProps {
  children: React.ReactNode;
}

export default function AuthGroupLayout({ children }: AuthGroupLayoutProps) {
  return (
    <main className="bg-background flex min-h-svh flex-col items-center justify-center p-4">
      {children}
    </main>
  );
}
