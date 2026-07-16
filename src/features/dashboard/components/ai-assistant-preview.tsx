"use client";

import * as React from "react";
import { AlertCircle, Sparkles } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Typography } from "@/components/ui/typography";
import { useAiJob } from "@/features/dashboard/hooks/use-ai-job";
import { useGenerateContent } from "@/features/dashboard/hooks/use-generate-content";

const SUGGESTED_PROMPTS = [
  "Write a blog post about WordPress security best practices",
  "Summarize my last 5 published posts",
  "Suggest 5 SEO titles for a post about site speed",
];

function AiAssistantPreview() {
  const [prompt, setPrompt] = React.useState("");
  const [jobId, setJobId] = React.useState<number | null>(null);

  const generate = useGenerateContent();
  const job = useAiJob(jobId);

  const isGenerating =
    generate.isPending ||
    job.data?.status === "pending" ||
    job.data?.status === "processing";

  const errorMessage = generate.isError
    ? generate.error instanceof Error
      ? generate.error.message
      : "Something went wrong while starting the generation."
    : job.data?.status === "failed"
      ? (job.data.error_message ??
        "The AI service couldn't complete this request.")
      : null;

  function handleGenerate() {
    if (!prompt.trim() || isGenerating) return;

    setJobId(null);
    generate.mutate(prompt, {
      onSuccess: (data) => setJobId(data.job_id),
    });
  }

  function handleReset() {
    setJobId(null);
    generate.reset();
    setPrompt("");
  }

  return (
    <Card data-slot="ai-assistant-preview">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Sparkles className="text-primary size-4" aria-hidden="true" />
          AI Assistant
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="ai-assistant-prompt">Prompt</Label>
          <Textarea
            id="ai-assistant-prompt"
            placeholder="Describe what you'd like AI to draft…"
            value={prompt}
            onChange={(event) => setPrompt(event.target.value)}
            disabled={isGenerating}
            rows={3}
          />
        </div>
        <div className="flex flex-wrap gap-2">
          {SUGGESTED_PROMPTS.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              onClick={() => setPrompt(suggestion)}
              disabled={isGenerating}
              className="border-border bg-background hover:bg-muted rounded-full border px-3 py-1 text-xs disabled:pointer-events-none disabled:opacity-50"
            >
              {suggestion}
            </button>
          ))}
        </div>

        {errorMessage ? (
          <div className="border-destructive/20 bg-destructive/5 flex items-start gap-2 rounded-lg border px-3 py-2">
            <AlertCircle
              className="text-destructive mt-0.5 size-4 shrink-0"
              aria-hidden="true"
            />
            <Typography variant="body-sm" className="text-destructive">
              {errorMessage}
            </Typography>
          </div>
        ) : null}

        {job.data?.status === "completed" && job.data.result ? (
          <div className="border-border bg-muted/30 rounded-lg border px-3 py-2">
            <Typography variant="body-sm" className="whitespace-pre-wrap">
              {job.data.result}
            </Typography>
          </div>
        ) : null}

        <div className="flex items-center justify-between gap-2">
          <Typography variant="caption">
            {isGenerating
              ? "Generating…"
              : job.data?.status === "completed"
                ? "Generated with Claude."
                : "Draft posts, summaries, and SEO titles with AI."}
          </Typography>
          {job.data?.status === "completed" ? (
            <Button type="button" variant="outline" onClick={handleReset}>
              New prompt
            </Button>
          ) : (
            <Button
              type="button"
              onClick={handleGenerate}
              disabled={!prompt.trim()}
              loading={isGenerating}
            >
              Generate
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

export { AiAssistantPreview };
