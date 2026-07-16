import { useMutation } from "@tanstack/react-query";

import { generateContent } from "@/services/api/ai.service";

export function useGenerateContent() {
  return useMutation({
    mutationFn: (prompt: string) => generateContent(prompt),
  });
}
