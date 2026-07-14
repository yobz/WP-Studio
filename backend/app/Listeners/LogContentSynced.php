<?php

namespace App\Listeners;

use App\Events\ContentSynced;
use Illuminate\Support\Facades\Log;

class LogContentSynced
{
    public function handle(ContentSynced $event): void
    {
        Log::info('Content synced', [
            'site_id' => $event->site->id,
            'content_type' => $event->contentType,
            'created' => $event->created,
            'updated' => $event->updated,
            'skipped' => $event->skipped,
            'failed' => $event->failed,
        ]);
    }
}
