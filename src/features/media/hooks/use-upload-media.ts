import { useMutation, useQueryClient } from "@tanstack/react-query";

import { uploadMedia } from "@/services/api/media.service";

export function useUploadMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ file, altText }: { file: File; altText?: string }) =>
      uploadMedia(file, altText),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
    },
  });
}
