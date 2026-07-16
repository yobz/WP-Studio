import { apiFetch } from "@/lib/api-client";

export type AiJobStatus = "pending" | "processing" | "completed" | "failed";

export interface AiJob {
  id: number;
  status: AiJobStatus;
  prompt: string;
  result: string | null;
  error_message: string | null;
  model: string | null;
  input_tokens: number | null;
  output_tokens: number | null;
  attempted_at: string | null;
  completed_at: string | null;
  created_at: string | null;
}

export interface GenerationQueuedResponse {
  status: "queued";
  job_id: number;
}

export async function generateContent(
  prompt: string,
): Promise<GenerationQueuedResponse> {
  return apiFetch<GenerationQueuedResponse>("/api/v1/ai/generate", {
    method: "POST",
    body: JSON.stringify({ prompt }),
  });
}

export async function getAiJob(id: number): Promise<AiJob> {
  return apiFetch<AiJob>(`/api/v1/ai/jobs/${id}`);
}
