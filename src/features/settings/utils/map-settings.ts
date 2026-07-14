import type { ApiSettings } from "@/services/api/settings.service";
import type { Settings } from "@/features/settings/types/settings.types";

export function mapSettings(settings: ApiSettings): Settings {
  return {
    workspaceName: settings.workspace.name,
    workspaceSlug: settings.workspace.slug,
    memberCount: settings.workspace.member_count,
    userName: settings.user.name,
    userEmail: settings.user.email,
    userRole: settings.user.role,
  };
}
