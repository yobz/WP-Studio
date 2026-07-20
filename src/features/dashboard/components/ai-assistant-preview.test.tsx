import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { renderWithQueryClient } from "@/test/render";
import { AiAssistantPreview } from "./ai-assistant-preview";

vi.mock("@/services/api/ai.service", () => ({
  generateContent: vi.fn(),
  getAiJob: vi.fn(),
}));

const { generateContent, getAiJob } = await import("@/services/api/ai.service");

describe("AiAssistantPreview", () => {
  beforeEach(() => {
    vi.mocked(generateContent).mockReset();
    vi.mocked(getAiJob).mockReset();
  });

  it("disables Generate until a prompt is entered", async () => {
    const user = userEvent.setup();
    renderWithQueryClient(<AiAssistantPreview />);

    expect(screen.getByRole("button", { name: "Generate" })).toBeDisabled();

    await user.type(screen.getByLabelText("Prompt"), "Write a post");

    expect(screen.getByRole("button", { name: "Generate" })).toBeEnabled();
  });

  it("fills the prompt when a suggested prompt is clicked", async () => {
    const user = userEvent.setup();
    renderWithQueryClient(<AiAssistantPreview />);

    await user.click(screen.getByText("Summarize my last 5 published posts"));

    expect(screen.getByLabelText("Prompt")).toHaveValue(
      "Summarize my last 5 published posts",
    );
  });

  it("shows the generated result once the job completes", async () => {
    vi.mocked(generateContent).mockResolvedValueOnce({
      status: "queued",
      job_id: 1,
    });
    vi.mocked(getAiJob).mockResolvedValueOnce({
      id: 1,
      status: "completed",
      prompt: "Write a post",
      result: "Here is your draft.",
      error_message: null,
      model: "claude-opus-4-8",
      input_tokens: 10,
      output_tokens: 20,
      attempted_at: null,
      completed_at: null,
      created_at: null,
    });
    const user = userEvent.setup();
    renderWithQueryClient(<AiAssistantPreview />);

    await user.type(screen.getByLabelText("Prompt"), "Write a post");
    await user.click(screen.getByRole("button", { name: "Generate" }));

    expect(await screen.findByText("Here is your draft.")).toBeInTheDocument();
    expect(screen.getByText("Generated with Claude.")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "New prompt" }),
    ).toBeInTheDocument();
  });

  it("shows an inline error and lets the user retry after a failed job", async () => {
    vi.mocked(generateContent).mockResolvedValueOnce({
      status: "queued",
      job_id: 2,
    });
    vi.mocked(getAiJob).mockResolvedValueOnce({
      id: 2,
      status: "failed",
      prompt: "Write a post",
      result: null,
      error_message: "AI generation is temporarily unavailable.",
      model: null,
      input_tokens: null,
      output_tokens: null,
      attempted_at: null,
      completed_at: null,
      created_at: null,
    });
    const user = userEvent.setup();
    renderWithQueryClient(<AiAssistantPreview />);

    await user.type(screen.getByLabelText("Prompt"), "Write a post");
    await user.click(screen.getByRole("button", { name: "Generate" }));

    expect(
      await screen.findByText("AI generation is temporarily unavailable."),
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.getByRole("button", { name: "Generate" })).toBeEnabled(),
    );
  });

  it("resets to a blank prompt when New prompt is clicked", async () => {
    vi.mocked(generateContent).mockResolvedValueOnce({
      status: "queued",
      job_id: 3,
    });
    vi.mocked(getAiJob).mockResolvedValueOnce({
      id: 3,
      status: "completed",
      prompt: "Write a post",
      result: "Draft text.",
      error_message: null,
      model: "claude-opus-4-8",
      input_tokens: 5,
      output_tokens: 15,
      attempted_at: null,
      completed_at: null,
      created_at: null,
    });
    const user = userEvent.setup();
    renderWithQueryClient(<AiAssistantPreview />);

    await user.type(screen.getByLabelText("Prompt"), "Write a post");
    await user.click(screen.getByRole("button", { name: "Generate" }));
    await screen.findByText("Draft text.");

    await user.click(screen.getByRole("button", { name: "New prompt" }));

    expect(screen.getByLabelText("Prompt")).toHaveValue("");
    expect(screen.queryByText("Draft text.")).not.toBeInTheDocument();
  });
});
