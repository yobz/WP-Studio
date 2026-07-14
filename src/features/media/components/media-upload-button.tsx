"use client";

import * as React from "react";
import { Upload } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Typography } from "@/components/ui/typography";
import { useUploadMedia } from "@/features/media/hooks/use-upload-media";
import { ApiError } from "@/lib/api-client";

const ACCEPTED_MIME_TYPES = "image/jpeg,image/png,image/gif,image/webp";

function MediaUploadButton() {
  const inputRef = React.useRef<HTMLInputElement>(null);
  const upload = useUploadMedia();
  const [error, setError] = React.useState<string | null>(null);

  function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;

    setError(null);
    upload.mutate(
      { file },
      {
        onError: (err) => {
          setError(
            err instanceof ApiError
              ? err.message
              : "Something went wrong. Please try again.",
          );
        },
      },
    );
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <Button
        onClick={() => inputRef.current?.click()}
        loading={upload.isPending}
      >
        <Upload data-icon="inline-start" />
        Upload
      </Button>
      <input
        ref={inputRef}
        type="file"
        accept={ACCEPTED_MIME_TYPES}
        onChange={handleChange}
        className="sr-only"
        aria-label="Upload media file"
      />
      {error ? (
        <Typography variant="caption" role="alert" className="text-destructive">
          {error}
        </Typography>
      ) : null}
    </div>
  );
}

export { MediaUploadButton };
