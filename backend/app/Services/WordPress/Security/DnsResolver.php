<?php

namespace App\Services\WordPress\Security;

class DnsResolver
{
    /**
     * Resolves a hostname to every IPv4/IPv6 address it currently points
     * at. Returns an empty array on resolution failure — an unresolvable
     * host is a connectivity problem the WordPress client will surface
     * naturally, not a safety concern this validator needs to reject.
     *
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
