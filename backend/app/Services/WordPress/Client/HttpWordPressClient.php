<?php

namespace App\Services\WordPress\Client;

use App\Services\WordPress\Authentication\ApplicationPasswordAuthenticator;
use App\Services\WordPress\Contracts\WordPressClientContract;
use App\Services\WordPress\DTO\WordPressSiteInfo;
use App\Services\WordPress\Exceptions\WordPressAuthenticationException;
use App\Services\WordPress\Exceptions\WordPressConnectionException;
use App\Services\WordPress\Exceptions\WordPressResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpWordPressClient implements WordPressClientContract
{
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly ApplicationPasswordAuthenticator $authenticator,
    ) {}

    public function fetchSiteInfo(string $url, string $username, string $applicationPassword): WordPressSiteInfo
    {
        $baseUrl = rtrim($url, '/');

        $index = $this->fetchRequired("{$baseUrl}/wp-json/", authenticated: false, username: $username, applicationPassword: $applicationPassword);
        $siteName = $index['name'] ?? null;
        if (! is_string($siteName) || $siteName === '') {
            throw new WordPressResponseException('the site index did not include a site name — is this a WordPress site?');
        }

        $settings = $this->fetchRequired("{$baseUrl}/wp-json/wp/v2/settings", authenticated: true, username: $username, applicationPassword: $applicationPassword);

        return new WordPressSiteInfo(
            siteName: $siteName,
            wordpressVersion: null,
            phpVersion: null,
            activeTheme: $this->fetchActiveTheme($baseUrl, $username, $applicationPassword),
            pluginCount: $this->fetchCount("{$baseUrl}/wp-json/wp/v2/plugins", $username, $applicationPassword),
            userCount: $this->fetchUserCount($baseUrl, $username, $applicationPassword),
            timezone: is_string($settings['timezone'] ?? null) ? $settings['timezone'] : null,
            language: is_string($settings['language'] ?? null) ? $settings['language'] : null,
        );
    }

    private function fetchActiveTheme(string $baseUrl, string $username, string $applicationPassword): ?string
    {
        $themes = $this->fetchOptional("{$baseUrl}/wp-json/wp/v2/themes", $username, $applicationPassword);
        if (! is_array($themes)) {
            return null;
        }

        foreach ($themes as $theme) {
            if (($theme['status'] ?? null) === 'active') {
                $name = $theme['name']['rendered'] ?? null;

                return is_string($name) ? $name : null;
            }
        }

        return null;
    }

    private function fetchCount(string $endpoint, string $username, string $applicationPassword): ?int
    {
        $items = $this->fetchOptional($endpoint, $username, $applicationPassword);

        return is_array($items) ? count($items) : null;
    }

    private function fetchUserCount(string $baseUrl, string $username, string $applicationPassword): ?int
    {
        $response = $this->request("{$baseUrl}/wp-json/wp/v2/users", ['per_page' => 1], $username, $applicationPassword);
        if ($response === null || $response->failed()) {
            return null;
        }

        $total = $response->header('X-WP-Total');

        return $total !== '' && is_numeric($total) ? (int) $total : null;
    }

    private function fetchRequired(string $endpoint, bool $authenticated, string $username, string $applicationPassword): array
    {
        $response = $this->request($endpoint, [], $authenticated ? $username : null, $authenticated ? $applicationPassword : null);

        if ($response === null) {
            throw new WordPressConnectionException('the request timed out or the host could not be reached.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new WordPressAuthenticationException;
        }

        if ($response->failed()) {
            throw new WordPressResponseException("received HTTP {$response->status()}.");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new WordPressResponseException('the response was not valid JSON.');
        }

        return $body;
    }

    private function fetchOptional(string $endpoint, string $username, string $applicationPassword): mixed
    {
        try {
            $response = $this->request($endpoint, [], $username, $applicationPassword);
        } catch (Throwable) {
            return null;
        }

        if ($response === null || $response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    private function request(string $endpoint, array $query, ?string $username, ?string $applicationPassword): ?Response
    {
        $request = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->retry(2, 200, when: fn (Throwable $e) => $e instanceof ConnectionException, throw: false)
            ->acceptJson();

        if ($username !== null && $applicationPassword !== null) {
            $request = $this->authenticator->authenticate($request, $username, $applicationPassword);
        }

        try {
            return $request->get($endpoint, $query);
        } catch (ConnectionException) {
            return null;
        }
    }
}
