<?php

namespace App\Listeners;

use App\Events\SiteConnected;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder listener demonstrating the event pattern this milestone
 * establishes — not queued (`ShouldQueue`) yet, since there's nothing
 * slow enough here to warrant it; revisit once this listener does
 * real work (e.g. triggering an initial WordPress sync).
 */
class LogSiteConnected
{
    public function handle(SiteConnected $event): void
    {
        Log::info('Site connected', ['site_id' => $event->site->id, 'name' => $event->site->name]);
    }
}
