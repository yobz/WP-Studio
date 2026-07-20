import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { renderWithQueryClient } from "@/test/render";
import { ApiError } from "@/lib/api-client";
import { LoginForm } from "./login-form";

const replace = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/authentication/services/auth.service", () => ({
  login: vi.fn(),
}));

const { login } =
  await import("@/features/authentication/services/auth.service");

describe("LoginForm", () => {
  beforeEach(() => {
    replace.mockClear();
    vi.mocked(login).mockReset();
  });

  it("shows validation errors for an empty submission without calling the API", async () => {
    const user = userEvent.setup();
    renderWithQueryClient(<LoginForm />);

    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Email is required")).toBeInTheDocument();
    expect(screen.getByText("Password is required")).toBeInTheDocument();
    expect(login).not.toHaveBeenCalled();
  });

  it("redirects to /dashboard on successful login", async () => {
    vi.mocked(login).mockResolvedValueOnce({
      id: 1,
      name: "Test User",
      email: "test@example.com",
      workspaces: [],
      current_workspace_id: null,
    });
    const user = userEvent.setup();
    renderWithQueryClient(<LoginForm />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(replace).toHaveBeenCalledWith("/dashboard"));
    expect(login).toHaveBeenCalledWith({
      email: "test@example.com",
      password: "password",
    });
  });

  it("shows a specific message for rejected credentials", async () => {
    vi.mocked(login).mockRejectedValueOnce(
      new ApiError("INVALID_CREDENTIALS", "Invalid credentials", 401),
    );
    const user = userEvent.setup();
    renderWithQueryClient(<LoginForm />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "wrong");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(
      await screen.findByText("Incorrect email or password."),
    ).toBeInTheDocument();
    expect(replace).not.toHaveBeenCalled();
  });

  it("shows a generic message for a non-credential failure", async () => {
    vi.mocked(login).mockRejectedValueOnce(new Error("Network down"));
    const user = userEvent.setup();
    renderWithQueryClient(<LoginForm />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(
      await screen.findByText("Something went wrong. Please try again."),
    ).toBeInTheDocument();
  });
});
