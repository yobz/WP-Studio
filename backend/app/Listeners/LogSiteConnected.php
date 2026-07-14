<?php

namespace App\Listeners;

use App\Events\SiteConnected;
use Illuminate\Support\Facades\Log;

class LogSiteConnected
{
    public function handle(SiteConnected $event): void
    {
        Log::info('Site connected', ['site_id' => $event->site->id, 'name' => $event->site->name]);
    }
}
