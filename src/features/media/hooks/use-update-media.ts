import { useMutation, useQueryClient } from "@tanstack/react-query";

import { updateMedia } from "@/services/api/media.service";

export function useUpdateMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, altText }: { id: number; altText: string }) =>
      updateMedia(id, altText),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
    },
  });
}
