<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $workspace = Workspace::factory()->create([
            'name' => 'Acme Inc.',
            'slug' => 'acme-inc',
        ]);
        $workspace->users()->attach($user, ['role' => WorkspaceRole::Owner->value]);

        $acmeBlog = Site::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Acme Blog',
            'url' => 'https://acmeblog.example.com',
            'wordpress_version' => '6.7.1',
            'theme' => 'Twenty Twenty-Five',
            'php_version' => '8.2',
            'plugin_updates_available' => 3,
            'plugin_count' => 18,
            'user_count' => 4,
            'timezone' => 'America/New_York',
            'language' => 'en_US',
            'storage_used_mb' => 2458,
            'storage_limit_mb' => 10240,
        ]);
        $acmeBlog->credential()->create([
            'wp_username' => 'admin',
            'application_password' => 'demo demo demo demo demo demo',
        ]);

        $acmeBlog->posts()->createMany([
            ...collect(range(1, 6))->map(fn () => [
                'title' => fake()->sentence(6),
                'status' => 'published',
                'published_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ])->all(),
            ['title' => 'Q3 Product Roadmap', 'status' => 'draft', 'published_at' => null],
            ['title' => 'Holiday Marketing Ideas', 'status' => 'in-review', 'published_at' => null],
        ]);
        $this->seedTrendingSnapshots($acmeBlog, startingVisitors: 420, endingVisitors: 14204);

        $portfolioSite = Site::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Portfolio Site',
            'url' => 'https://portfolio.example.com',
            'wordpress_version' => '6.7.1',
            'theme' => 'Astra',
            'php_version' => '8.3',
            'plugin_updates_available' => 0,
            'plugin_count' => 9,
            'user_count' => 1,
            'timezone' => 'America/Los_Angeles',
            'language' => 'en_US',
            'storage_used_mb' => 512,
            'storage_limit_mb' => 10240,
        ]);
        $portfolioSite->credential()->create([
            'wp_username' => 'admin',
            'application_password' => 'demo demo demo demo demo demo',
        ]);

        $portfolioSite->posts()->createMany([
            ...collect(range(1, 3))->map(fn () => [
                'title' => fake()->sentence(6),
                'status' => 'published',
                'published_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ])->all(),
            ['title' => 'Customer Success Stories', 'status' => 'draft', 'published_at' => null],
        ]);
        $this->seedTrendingSnapshots($portfolioSite, startingVisitors: 3200, endingVisitors: 4000);

        Site::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Docs Site',
            'url' => 'https://docs.example.com',
        ]);
        Site::factory()->disconnected()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Staging Site',
            'url' => 'https://staging.example.com',
        ]);
    }

    private function seedTrendingSnapshots(Site $site, int $startingVisitors, int $endingVisitors): void
    {
        $days = 28;
        $step = ($endingVisitors - $startingVisitors) / max($days - 1, 1);

        foreach (range(0, $days - 1) as $dayOffset) {
            $visitors = (int) round($startingVisitors + $step * $dayOffset);

            $site->analyticsSnapshots()->create([
                'snapshot_date' => now()->subDays($days - 1 - $dayOffset)->format('Y-m-d'),
                'visitors' => max(0, $visitors + fake()->numberBetween(-40, 40)),
                'posts_published' => fake()->boolean(20) ? 1 : 0,
                'storage_used_mb' => $site->storage_used_mb,
            ]);
        }
    }
}
