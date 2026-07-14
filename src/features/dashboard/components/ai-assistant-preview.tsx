"use client";

import * as React from "react";
import { Sparkles } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Typography } from "@/components/ui/typography";

const SUGGESTED_PROMPTS = [
  "Write a blog post about WordPress security best practices",
  "Summarize my last 5 published posts",
  "Suggest 5 SEO titles for a post about site speed",
];

function AiAssistantPreview() {
  const [prompt, setPrompt] = React.useState("");

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
            rows={3}
          />
        </div>
        <div className="flex flex-wrap gap-2">
          {SUGGESTED_PROMPTS.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              onClick={() => setPrompt(suggestion)}
              className="border-border bg-background hover:bg-muted rounded-full border px-3 py-1 text-xs"
            >
              {suggestion}
            </button>
          ))}
        </div>
        <div className="flex items-center justify-between gap-2">
          <Typography variant="caption">
            AI generation isn&apos;t connected yet — this is a preview of the
            experience.
          </Typography>
          <Button type="button" disabled>
            Generate
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

export { AiAssistantPreview };
