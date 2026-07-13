const RELATIVE_TIME_UNITS: { unit: Intl.RelativeTimeFormatUnit; ms: number }[] =
  [
    { unit: "year", ms: 365 * 24 * 60 * 60 * 1000 },
    { unit: "month", ms: 30 * 24 * 60 * 60 * 1000 },
    { unit: "day", ms: 24 * 60 * 60 * 1000 },
    { unit: "hour", ms: 60 * 60 * 1000 },
    { unit: "minute", ms: 60 * 1000 },
  ];

const relativeTimeFormatter = new Intl.RelativeTimeFormat("en-US", {
  numeric: "auto",
});

/**
 * Formats an ISO timestamp as "2 hours ago", "yesterday", etc. Falls back
 * to "just now" for anything under a minute so activity that just
 * happened doesn't render as "0 minutes ago".
 */
export function formatRelativeTime(isoTimestamp: string): string {
  const diffMs = new Date(isoTimestamp).getTime() - Date.now();

  for (const { unit, ms } of RELATIVE_TIME_UNITS) {
    if (Math.abs(diffMs) >= ms) {
      return relativeTimeFormatter.format(Math.round(diffMs / ms), unit);
    }
  }

  return "just now";
}
