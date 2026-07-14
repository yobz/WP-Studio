import { useQuery } from "@tanstack/react-query";

import { mapSettings } from "@/features/settings/utils/map-settings";
import { getSettings } from "@/services/api/settings.service";

export function useSettings() {
  return useQuery({
    queryKey: ["settings"],
    queryFn: async () => mapSettings(await getSettings()),
  });
}
