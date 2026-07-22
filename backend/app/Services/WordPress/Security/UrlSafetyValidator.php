<?php

namespace App\Services\WordPress\Security;

use App\Services\WordPress\Exceptions\WordPressConnectionException;
use Illuminate\Support\Str;

class UrlSafetyValidator
{
    private const BLOCKED_HOSTNAME_SUFFIXES = ['.localhost', '.local', '.internal'];

    public function __construct(
        private readonly DnsResolver $dnsResolver,
    ) {}

    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new WordPressConnectionException('the URL must be a valid http or https address.');
        }

        $host = Str::lower($host);

        if ($host === 'localhost' || $this->hasBlockedSuffix($host)) {
            throw new WordPressConnectionException('connecting to a local hostname is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return;
        }

        // $host is a hostname, not a literal IP — the check above never ran.
        // Resolve it and validate every address it currently points at, so a
        // hostname pointed at an internal IP (e.g. via a wildcard DNS record
        // or a misconfigured/malicious A record) is rejected the same way a
        // literal private IP already is.
        foreach ($this->dnsResolver->resolve($host) as $ip) {
            $this->assertPublicIp($ip);
        }
    }

    private function assertPublicIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new WordPressConnectionException('the site address resolves to a private or reserved network address, which is not allowed.');
        }
    }

    private function hasBlockedSuffix(string $host): bool
    {
        foreach (self::BLOCKED_HOSTNAME_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
