<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder — nothing dispatches this yet. Milestone 9 (WordPress
 * Integration) is where a real "connect a site" flow exists to
 * dispatch it from; it's added now so the event/listener pattern
 * (and `LogSiteConnected` below) exists and is documented before
 * the first real usage, rather than being invented ad hoc later.
 */
class SiteConnected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Site $site) {}
}
