<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * Seeds one realistic workspace end-to-end — enough for the Dashboard
 * summary and the Sites/Posts CRUD endpoints to return non-trivial,
 * relationally-correct data locally without a real WordPress
 * connection. Roughly mirrors the shape of the frontend's mock fixture
 * data (src/services/mock/dashboard.mock-data.ts) so the real
 * endpoints "feel" like a plausible replacement, not a coincidence.
 * Supersedes Milestone 6's `SiteSeeder`, which had no `Workspace` to
 * attach sites to.
 */
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
            'wordpress_version' => '6.7.1',
            'theme' => 'Twenty Twenty-Five',
            'plugin_updates_available' => 3,
            'storage_used_mb' => 2458,
            'storage_limit_mb' => 10240,
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
            'wordpress_version' => '6.7.1',
            'theme' => 'Astra',
            'plugin_updates_available' => 0,
            'storage_used_mb' => 512,
            'storage_limit_mb' => 10240,
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

        // A third "connected" site with no posts or snapshots yet, and
        // a disconnected one — exercises the KPI/trend aggregation
        // against a less tidy dataset than "every site has full
        // history."
        Site::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Docs Site']);
        Site::factory()->disconnected()->create(['workspace_id' => $workspace->id, 'name' => 'Staging Site']);
    }

    /**
     * 28 days of gently-trending snapshots ending "today" — two full
     * 14-day windows, so `DashboardService` can compute a real
     * period-over-period trend (current 14 days vs. the prior 14) with
     * an actual non-empty baseline on both sides, not just a single
     * point-in-time number. See docs/adr/0005-domain-model.md.
     */
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
