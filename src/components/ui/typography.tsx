import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

const typographyVariants = cva("text-foreground", {
  variants: {
    variant: {
      display: "text-4xl font-semibold tracking-tight md:text-5xl",
      h1: "text-3xl font-semibold tracking-tight",
      h2: "text-2xl font-semibold tracking-tight",
      h3: "text-xl font-semibold tracking-tight",
      h4: "text-lg font-medium tracking-tight",
      body: "text-sm font-normal",
      "body-sm": "text-xs font-normal",
      caption: "text-xs text-muted-foreground",
      label: "text-sm leading-none font-medium",
      code: "rounded bg-muted px-1 py-0.5 font-mono text-sm",
    },
  },
  defaultVariants: {
    variant: "body",
  },
});

type TypographyVariant = NonNullable<
  VariantProps<typeof typographyVariants>["variant"]
>;

const defaultElement: Record<TypographyVariant, React.ElementType> = {
  display: "h1",
  h1: "h1",
  h2: "h2",
  h3: "h3",
  h4: "h4",
  body: "p",
  "body-sm": "p",
  caption: "span",
  label: "span",
  code: "code",
};

type TypographyProps<T extends React.ElementType = "p"> = {
  as?: T;
  variant?: TypographyVariant;
  className?: string;
} & Omit<React.ComponentPropsWithoutRef<T>, "as" | "className">;

function Typography<T extends React.ElementType = "p">({
  as,
  variant = "body",
  className,
  ...props
}: TypographyProps<T>) {
  const Component = as ?? defaultElement[variant];

  return (
    <Component
      data-slot="typography"
      data-variant={variant}
      className={cn(typographyVariants({ variant }), className)}
      {...props}
    />
  );
}

export { Typography, typographyVariants };
