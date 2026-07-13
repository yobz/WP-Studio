<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PublishingJob;

/**
 * Placeholder — `schedule()` only records intent (a `PublishingJob`
 * row, status `pending`); nothing actually processes it yet, since no
 * queue worker or WordPress REST client exists. This is deliberately
 * where a future `ProcessPublishingJob` queued job would be dispatched
 * from (`ProcessPublishingJob::dispatch($job)`), once one exists — the
 * method boundary is drawn here now so that addition is additive, not
 * a refactor. See docs/adr/0005-domain-model.md.
 */
class PublishingService
{
    public function schedule(Post $post): PublishingJob
    {
        return $post->publishingJobs()->create([
            'status' => 'pending',
        ]);
    }
}
