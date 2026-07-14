<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PublishingJob;

class PublishingService
{
    public function schedule(Post $post): PublishingJob
    {
        return $post->publishingJobs()->create([
            'status' => 'pending',
        ]);
    }
}
