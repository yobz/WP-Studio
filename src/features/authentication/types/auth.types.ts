export interface AuthWorkspace {
  id: number;
  name: string;
  slug: string;
  role: "owner" | "admin" | "member";
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  workspaces: AuthWorkspace[];
  current_workspace_id: number | null;
}

export interface LoginCredentials {
  email: string;
  password: string;
}
