import type * as React from "react";

interface ProtectedLayoutProps {
  children: React.ReactNode;
}

/**
 * Placeholder auth boundary for Milestone 8 (Authentication). Currently a
 * pass-through — no session check exists yet. Wiring it in now (rather than
 * adding it later) means every shell route already sits behind this
 * boundary, so Milestone 8 only has to implement the check itself here,
 * not retrofit every route.
 */
function ProtectedLayout({ children }: ProtectedLayoutProps) {
  return children;
}

export { ProtectedLayout };
