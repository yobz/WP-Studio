"use client";

import { AlertCircle } from "lucide-react";

import { EmptyState } from "@/components/common/empty-state";
import { LoadingState } from "@/components/common/loading-state";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Typography } from "@/components/ui/typography";
import { useSettings } from "@/features/settings/hooks/use-settings";

function SettingsSummary() {
  const {
    data: settings,
    isPending,
    isError,
    refetch,
    isRefetching,
  } = useSettings();

  if (isPending) {
    return <LoadingState message="Loading settings…" />;
  }

  if (isError || !settings) {
    return (
      <EmptyState
        icon={AlertCircle}
        title="Couldn't load settings"
        description="Something went wrong while loading your workspace settings."
        action={
          <Button
            variant="outline"
            size="sm"
            onClick={() => refetch()}
            loading={isRefetching}
          >
            Try again
          </Button>
        }
      />
    );
  }

  return (
    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Workspace</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div>
            <Typography variant="caption">Name</Typography>
            <Typography variant="body">{settings.workspaceName}</Typography>
          </div>
          <div>
            <Typography variant="caption">Slug</Typography>
            <Typography variant="body">{settings.workspaceSlug}</Typography>
          </div>
          <div>
            <Typography variant="caption">Members</Typography>
            <Typography variant="body">{settings.memberCount}</Typography>
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Your Account</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div>
            <Typography variant="caption">Name</Typography>
            <Typography variant="body">{settings.userName}</Typography>
          </div>
          <div>
            <Typography variant="caption">Email</Typography>
            <Typography variant="body">{settings.userEmail}</Typography>
          </div>
          <div>
            <Typography variant="caption">Role</Typography>
            <Typography variant="body" className="capitalize">
              {settings.userRole ?? "Unknown"}
            </Typography>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export { SettingsSummary };
