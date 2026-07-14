<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteConnected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Site $site) {}
}
