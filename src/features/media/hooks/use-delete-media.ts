import { useMutation, useQueryClient } from "@tanstack/react-query";

import { deleteMedia } from "@/services/api/media.service";

export function useDeleteMedia() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => deleteMedia(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["media"] });
    },
  });
}
