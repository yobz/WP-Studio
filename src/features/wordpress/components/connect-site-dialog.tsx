"use client";

import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { Plus } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Typography } from "@/components/ui/typography";
import { useConnectSite } from "@/features/wordpress/hooks/use-connect-site";
import { ApiError } from "@/lib/api-client";

const connectSiteSchema = z.object({
  name: z.string().min(1, "Name is required").max(255),
  url: z
    .string()
    .min(1, "Site URL is required")
    .url("Enter a full URL, including https://"),
  wp_username: z.string().min(1, "WordPress username is required"),
  application_password: z
    .string()
    .min(10, "Enter the full Application Password WordPress generated"),
});

type ConnectSiteFormValues = z.infer<typeof connectSiteSchema>;

function ConnectSiteDialog() {
  const [open, setOpen] = React.useState(false);
  const [formError, setFormError] = React.useState<string | null>(null);
  const connect = useConnectSite();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ConnectSiteFormValues>({
    resolver: zodResolver(connectSiteSchema),
    defaultValues: {
      name: "",
      url: "",
      wp_username: "",
      application_password: "",
    },
  });

  function onSubmit(values: ConnectSiteFormValues) {
    setFormError(null);
    connect.mutate(values, {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
      onError: (error) => {
        setFormError(
          error instanceof ApiError
            ? error.message
            : "Something went wrong. Please try again.",
        );
      },
    });
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        setOpen(nextOpen);
        if (!nextOpen) {
          reset();
          setFormError(null);
        }
      }}
    >
      <DialogTrigger render={<Button />}>
        <Plus data-icon="inline-start" />
        Connect Site
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Connect a WordPress site</DialogTitle>
          <DialogDescription>
            Requires an Application Password — generate one under WordPress
            Admin → Users → Profile → Application Passwords.
          </DialogDescription>
        </DialogHeader>

        <form
          onSubmit={handleSubmit(onSubmit)}
          noValidate
          className="flex flex-col gap-4"
        >
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="connect-name">Name</Label>
            <Input
              id="connect-name"
              placeholder="My Blog"
              aria-invalid={!!errors.name}
              {...register("name")}
            />
            {errors.name ? (
              <Typography variant="caption" className="text-destructive">
                {errors.name.message}
              </Typography>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="connect-url">Site URL</Label>
            <Input
              id="connect-url"
              type="url"
              placeholder="https://example.com"
              aria-invalid={!!errors.url}
              {...register("url")}
            />
            {errors.url ? (
              <Typography variant="caption" className="text-destructive">
                {errors.url.message}
              </Typography>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="connect-username">WordPress Username</Label>
            <Input
              id="connect-username"
              autoComplete="off"
              aria-invalid={!!errors.wp_username}
              {...register("wp_username")}
            />
            {errors.wp_username ? (
              <Typography variant="caption" className="text-destructive">
                {errors.wp_username.message}
              </Typography>
            ) : null}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="connect-password">Application Password</Label>
            <Input
              id="connect-password"
              type="password"
              autoComplete="off"
              placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
              aria-invalid={!!errors.application_password}
              {...register("application_password")}
            />
            {errors.application_password ? (
              <Typography variant="caption" className="text-destructive">
                {errors.application_password.message}
              </Typography>
            ) : null}
          </div>

          {formError ? (
            <Typography
              variant="body-sm"
              role="alert"
              className="text-destructive"
            >
              {formError}
            </Typography>
          ) : null}

          <DialogFooter>
            <Button type="submit" loading={connect.isPending}>
              Connect
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export { ConnectSiteDialog };
