<?php

namespace App\Services\WordPress;

use App\Enums\SiteStatus;
use App\Events\SiteConnected;
use App\Models\Site;
use App\Models\Workspace;
use App\Services\WordPress\Contracts\WordPressClientContract;
use App\Services\WordPress\DTO\WordPressSiteInfo;
use App\Services\WordPress\Exceptions\WordPressConnectionException;
use App\Services\WordPress\Exceptions\WordPressIntegrationException;
use App\Services\WordPress\Security\UrlSafetyValidator;

class SiteConnectionService
{
    public function __construct(
        private readonly WordPressClientContract $client,
        private readonly UrlSafetyValidator $urlSafety,
    ) {}

    public function connect(Workspace $workspace, array $attributes): Site
    {
        $this->urlSafety->assertSafe($attributes['url']);

        $info = $this->client->fetchSiteInfo(
            $attributes['url'],
            $attributes['wp_username'],
            $attributes['application_password'],
        );

        $site = $workspace->sites()->create([
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'status' => SiteStatus::Connected,
            ...$this->metadataFromInfo($info),
            'last_connected_at' => now(),
            'last_checked_at' => now(),
            'connection_error' => null,
        ]);

        $site->credential()->create([
            'wp_username' => $attributes['wp_username'],
            'application_password' => $attributes['application_password'],
        ]);

        SiteConnected::dispatch($site);

        return $site;
    }

    public function disconnect(Site $site): Site
    {
        $site->credential?->delete();

        $site->update([
            'status' => SiteStatus::Disconnected,
            'connection_error' => null,
        ]);

        return $site;
    }

    public function verifyConnection(Site $site): Site
    {
        return $this->syncFromWordPress($site);
    }

    public function refreshMetadata(Site $site): Site
    {
        return $this->syncFromWordPress($site);
    }

    private function syncFromWordPress(Site $site): Site
    {
        $credential = $site->credential;
        if ($credential === null) {
            throw new WordPressConnectionException('this site has no stored credential — reconnect it to continue.');
        }

        $this->urlSafety->assertSafe($site->url);

        try {
            $info = $this->client->fetchSiteInfo($site->url, $credential->wp_username, $credential->application_password);
        } catch (WordPressIntegrationException $e) {
            $site->update([
                'status' => SiteStatus::Error,
                'connection_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);

            throw $e;
        }

        $site->update([
            'status' => SiteStatus::Connected,
            ...$this->metadataFromInfo($info),
            'last_connected_at' => now(),
            'last_checked_at' => now(),
            'connection_error' => null,
        ]);

        return $site;
    }

    private function metadataFromInfo(WordPressSiteInfo $info): array
    {
        return [
            'wordpress_version' => $info->wordpressVersion,
            'php_version' => $info->phpVersion,
            'theme' => $info->activeTheme,
            'plugin_count' => $info->pluginCount,
            'user_count' => $info->userCount,
            'timezone' => $info->timezone,
            'language' => $info->language,
        ];
    }
}
