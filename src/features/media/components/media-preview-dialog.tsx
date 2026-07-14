"use client";

import * as React from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Typography } from "@/components/ui/typography";
import { useDeleteMedia } from "@/features/media/hooks/use-delete-media";
import { useUpdateMedia } from "@/features/media/hooks/use-update-media";
import { formatFileSize } from "@/features/media/utils/format-file-size";
import { ApiError } from "@/lib/api-client";
import type { ApiMedia } from "@/services/api/media.service";

const altTextSchema = z.object({
  alt_text: z.string().max(255),
});

type AltTextFormValues = z.infer<typeof altTextSchema>;

interface MediaPreviewDialogProps {
  item: ApiMedia | null;
  onOpenChange: (open: boolean) => void;
}

function MediaPreviewDialog({ item, onOpenChange }: MediaPreviewDialogProps) {
  const update = useUpdateMedia();
  const deleteMedia = useDeleteMedia();
  const [formError, setFormError] = React.useState<string | null>(null);

  const { register, handleSubmit, reset } = useForm<AltTextFormValues>({
    resolver: zodResolver(altTextSchema),
    values: { alt_text: item?.alt_text ?? "" },
  });

  function onSubmit(values: AltTextFormValues) {
    if (!item) return;
    setFormError(null);
    update.mutate(
      { id: item.id, altText: values.alt_text },
      {
        onError: (error) => {
          setFormError(
            error instanceof ApiError
              ? error.message
              : "Something went wrong. Please try again.",
          );
        },
      },
    );
  }

  function handleDelete() {
    if (!item) return;
    deleteMedia.mutate(item.id, {
      onSuccess: () => onOpenChange(false),
    });
  }

  return (
    <Dialog
      open={item !== null}
      onOpenChange={(nextOpen) => {
        onOpenChange(nextOpen);
        if (!nextOpen) {
          reset();
          setFormError(null);
        }
      }}
    >
      <DialogContent className="sm:max-w-lg">
        {item ? (
          <>
            <DialogHeader>
              <DialogTitle className="truncate">{item.filename}</DialogTitle>
            </DialogHeader>

            <div className="bg-muted overflow-hidden rounded-lg">
              {/* eslint-disable-next-line @next/next/no-img-element -- external/local storage URLs, not optimizable without configuring every possible disk host */}
              <img
                src={item.url}
                alt={item.alt_text || item.filename}
                className="max-h-80 w-full object-contain"
              />
            </div>

            <div className="flex items-center justify-between gap-2">
              <Typography variant="caption">
                {item.width && item.height
                  ? `${item.width}×${item.height} · `
                  : ""}
                {formatFileSize(item.size)} · {item.mime_type} · {item.source}
              </Typography>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={handleDelete}
                loading={deleteMedia.isPending}
              >
                Delete
              </Button>
            </div>

            <form
              onSubmit={handleSubmit(onSubmit)}
              noValidate
              className="flex flex-col gap-3"
            >
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="media-alt-text">Alt text</Label>
                <Textarea
                  id="media-alt-text"
                  rows={2}
                  placeholder="Describe this image for screen readers"
                  {...register("alt_text")}
                />
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
              {update.isSuccess ? (
                <Typography variant="body-sm" role="status">
                  Saved.
                </Typography>
              ) : null}

              <DialogFooter>
                <Button type="submit" loading={update.isPending}>
                  Save alt text
                </Button>
              </DialogFooter>
            </form>
          </>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

export { MediaPreviewDialog };
